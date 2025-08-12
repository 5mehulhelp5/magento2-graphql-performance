<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Schema\Schema;
use Sterk\GraphQlPerformance\Model\Cache\QueryCache;
use Sterk\GraphQlPerformance\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Plugin for caching GraphQL query results
 *
 * This plugin implements caching for GraphQL queries, improving performance by
 * serving cached results when available. It handles cache key generation,
 * cache tagging, and cache lifetime management based on query type.
 */
class QueryCachePlugin
{
    /**
     * @param QueryCache $queryCache Query cache service
     * @param Config $config Configuration service
     * @param LoggerInterface $logger Logger service
     */
    public function __construct(
        private readonly QueryCache $queryCache,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Around plugin for query processing
     *
     * @param QueryProcessor $subject Query processor instance
     * @param \Closure $proceed Original method
     * @param Schema $schema GraphQL schema
     * @param string $query GraphQL query
     * @param array|null $variables Query variables
     * @param array|null $contextValue Context value
     * @param array|null $rootValue Root value
     * @return array Query result
     */
    public function aroundProcess(
        QueryProcessor $subject,
        \Closure $proceed,
        Schema $schema,
        string $query,
        ?array $variables = null,
        ?array $contextValue = null,
        ?array $rootValue = null
    ): array {
        // Skip caching for mutations
        if ($this->isMutation($query)) {
            return $proceed($schema, $query, $variables, $contextValue, $rootValue);
        }

        // Try to get from cache
        $cachedResult = $this->queryCache->getQueryResult($query, $variables ?? []);
        if ($cachedResult !== null) {
            $this->logger->debug(
                'GraphQL query cache hit',
                [
                'query' => $query,
                'variables' => $variables
                ]
            );
            return $cachedResult;
        }

        // Execute query
        $result = $proceed($schema, $query, $variables, $contextValue, $rootValue);

        // Cache the result if no errors
        if (!isset($result['errors'])) {
            $lifetime = $this->getCacheLifetime($query);
            $tags = $this->getCacheTags($result);

            $this->queryCache->saveQueryResult(
                $query,
                $variables ?? [],
                $result,
                $tags,
                $lifetime
            );

            $this->logger->debug(
                'GraphQL query cached',
                [
                'query' => $query,
                'variables' => $variables,
                'lifetime' => $lifetime,
                'tags' => $tags
                ]
            );
        }

        return $result;
    }

    /**
     * Check if query is a mutation
     *
     * @param  string $query
     * @return bool
     */
    private function isMutation(string $query): bool
    {
        return stripos(trim($query), 'mutation') === 0;
    }

    /**
     * Get cache lifetime for query
     *
     * @param  string $query
     * @return int
     */
    private function getCacheLifetime(string $query): int
    {
        // Use different lifetimes based on query type
        if (stripos($query, 'products') !== false) {
            return $this->config->getFieldResolverConfig('product', 'cache_lifetime');
        }

        if (stripos($query, 'categories') !== false) {
            return $this->config->getFieldResolverConfig('category', 'cache_lifetime');
        }

        if (stripos($query, 'cart') !== false) {
            return $this->config->getFieldResolverConfig('cart', 'cache_lifetime');
        }

        return $this->config->getCacheLifetime();
    }

    /**
     * Get cache tags from result
     *
     * @param  array $result
     * @return array
     */
    private function getCacheTags(array $result): array
    {
        $tags = [QueryCache::CACHE_TAG];

        // Add entity-specific tags
        if (isset($result['data'])) {
            if (isset($result['data']['products'])) {
                $tags[] = 'catalog_product';
                foreach ($result['data']['products']['items'] ?? [] as $product) {
                    if (isset($product['id'])) {
                        $tags[] = 'catalog_product_' . $product['id'];
                    }
                }
            }

            if (isset($result['data']['categories'])) {
                $tags[] = 'catalog_category';
                foreach ($result['data']['categories']['items'] ?? [] as $category) {
                    if (isset($category['id'])) {
                        $tags[] = 'catalog_category_' . $category['id'];
                    }
                }
            }

            if (isset($result['data']['cart'])) {
                $tags[] = 'quote';
                if (isset($result['data']['cart']['id'])) {
                    $tags[] = 'quote_' . $result['data']['cart']['id'];
                }
            }
        }

        return array_unique($tags);
    }
}
