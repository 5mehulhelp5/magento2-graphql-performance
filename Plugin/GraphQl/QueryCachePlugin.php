<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\GraphQl\Schema;
use Magento\GraphQl\Model\Query\Context;
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
     * @param string|null $source GraphQL query source
     * @param Context|null $context Query context
     * @param array|null $variables Query variables
     * @param string|null $operationName Operation name
     * @param array|null $extensions GraphQL extensions
     * @return array Query result
     */
    public function aroundProcess(
        QueryProcessor $subject,
        \Closure $proceed,
        Schema $schema,
        ?string $source = null,
        ?Context $context = null,
        ?array $variables = null,
        ?string $operationName = null,
        ?array $extensions = null
    ): array {
        if (empty($source)) {
            return $proceed($schema, $source, $context, $variables, $operationName, $extensions);
        }

        // Skip caching for mutations
        if ($this->isMutation($source)) {
            return $proceed($schema, $source, $context, $variables, $operationName, $extensions);
        }

        // Try to get from cache
        $cachedResult = $this->queryCache->getQueryResult($source, $variables ?? []);
        if ($cachedResult !== null) {
            $this->logger->debug(
                'GraphQL query cache hit',
                [
                    'query' => $source,
                    'operation' => $operationName,
                    'variables' => $variables
                ]
            );
            return $cachedResult;
        }

        try {
            // Execute query
            $result = $proceed($schema, $source, $context, $variables, $operationName, $extensions);

            // Handle empty results for categories and products
            if (isset($result['data'])) {
                if (isset($result['data']['categories']) && empty($result['data']['categories']['items'])) {
                    $path = $variables['url'] ?? '';
                    return [
                        'data' => null,
                        'errors' => [
                            [
                                'message' => __('Category not found for path: %1', $path),
                                'extensions' => [
                                    'category' => 'graphql-no-such-entity',
                                    'path' => $path
                                ]
                            ]
                        ]
                    ];
                }

                if (isset($result['data']['products']) && empty($result['data']['products']['items'])) {
                    $categoryId = $variables['filters']['category_uid']['eq'] ?? '';
                    return [
                        'data' => [
                            'products' => [
                                'items' => [],
                                'total_count' => 0,
                                'page_info' => [
                                    'total_pages' => 0,
                                    'current_page' => 1,
                                    'page_size' => $variables['pageSize'] ?? 24
                                ]
                            ]
                        ]
                    ];
                }
            }

            // Cache the result if no errors
            if (!isset($result['errors'])) {
            $lifetime = $this->getCacheLifetime($source);
            $tags = $this->getCacheTags($result);

            $this->queryCache->saveQueryResult(
                $source,
                $variables ?? [],
                $result,
                $tags,
                $lifetime
            );

            $this->logger->debug(
                'GraphQL query cached',
                [
                    'query' => $source,
                    'operation' => $operationName,
                    'variables' => $variables,
                    'lifetime' => $lifetime,
                    'tags' => $tags
                ]
            );
        }

        return $result;
        } catch (\Exception $e) {
            $this->logger->error('GraphQL query error', [
                'query' => $source,
                'operation' => $operationName,
                'variables' => $variables,
                'error' => $e->getMessage()
            ]);
            return [
                'data' => null,
                'errors' => [
                    [
                        'message' => $e->getMessage(),
                        'extensions' => [
                            'category' => 'graphql'
                        ]
                    ]
                ]
            ];
        }
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
        // Use different lifetimes based on query type and fields
        if (stripos($query, 'products') !== false) {
            // Shorter cache for product queries with stock or price
            if (stripos($query, 'stock_status') !== false || stripos($query, 'price_range') !== false) {
                return 300; // 5 minutes for dynamic data
            }

            // Shorter cache for filtered product queries
            if (stripos($query, 'es_outlet_urun') !== false || stripos($query, 'es_webe_ozel') !== false) {
                return 600; // 10 minutes for filtered products
            }

            return $this->config->getResolverCacheLifetime('product');
        }

        if (stripos($query, 'categories') !== false) {
            // Longer cache for brand categories
            if (stripos($query, 'is_brand') !== false || stripos($query, 'manufacturer') !== false) {
                return 86400; // 24 hours for brand data
            }

            return $this->config->getResolverCacheLifetime('category');
        }

        if (stripos($query, 'cart') !== false) {
            return $this->config->getResolverCacheLifetime('cart');
        }

        // Special handling for Edip Saat custom queries
        if (stripos($query, 'brandCategories') !== false || stripos($query, 'GetAllCakmakCategories') !== false) {
            return 86400; // 24 hours for brand listings
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
