<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema;
use Sterk\GraphQlPerformance\Model\Performance\QueryOptimizer;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

/**
 * Plugin for optimizing GraphQL query performance
 *
 * This plugin handles query optimization, caching, and performance monitoring
 * for GraphQL queries. It attempts to serve cached results when available and
 * optimizes queries before execution to improve performance.
 */
class PerformancePlugin
{
    private const CACHE_LIFETIME = 3600;
    private const TIMING_KEY = 'query_processing';

    /**
     * @param QueryOptimizer $queryOptimizer Query optimization service
     * @param QueryTimer $queryTimer Query timing service
     * @param ResolverCache $cache Cache service for GraphQL resolvers
     * @param CacheKeyGenerator $cacheKeyGenerator Cache key generation service
     */
    public function __construct(
        private readonly QueryOptimizer $queryOptimizer,
        private readonly QueryTimer $queryTimer,
        private readonly ResolverCache $cache,
        private readonly CacheKeyGenerator $cacheKeyGenerator
    ) {
    }

    /**
     * Optimize query before processing
     *
     * @param QueryProcessor $subject Query processor instance
     * @param Schema $schema GraphQL schema
     * @param string|null $source GraphQL query source
     * @param ContextInterface|null $context Query context
     * @param array|null $variables Query variables
     * @param string|null $operationName Operation name
     * @param array|null $extensions GraphQL extensions
     * @return array
     */
    public function beforeProcess(
        QueryProcessor $subject,
        Schema $schema,
        ?string $source = null,
        ?ContextInterface $context = null,
        ?array $variables = null,
        ?string $operationName = null,
        ?array $extensions = null
    ): array {
        if (!$source) {
            return [$schema, $source, $context, $variables, $operationName, $extensions];
        }

        $this->queryTimer->start(self::TIMING_KEY);

        try {
            $result = $this->tryGetFromCache($source, $variables);
            if ($result !== null) {
                return [$schema, $source, $context, $variables, $operationName, $extensions, $result];
            }

            $optimizedQuery = $this->optimizeQuery($source, $variables);
            return [$schema, $optimizedQuery[0], $context, $optimizedQuery[1], $operationName, $extensions];
        } catch (\Exception $e) {
            return [$schema, $source, $context, $variables, $operationName, $extensions];
        }
    }

    /**
     * Process result after query execution
     *
     * @param QueryProcessor $subject Query processor instance
     * @param array $result Query result
     * @param Schema $schema GraphQL schema
     * @param string|null $source GraphQL query source
     * @param ContextInterface|null $context Query context
     * @param array|null $variables Query variables
     * @param string|null $operationName Operation name
     * @param array|null $extensions GraphQL extensions
     * @return array
     */
    public function afterProcess(
        QueryProcessor $subject,
        array $result,
        Schema $schema,
        ?string $source = null,
        ?ContextInterface $context = null,
        ?array $variables = null,
        ?string $operationName = null,
        ?array $extensions = null
    ): array {
        if ($source) {
            try {
                $this->cacheResultIfValid($result, $source, $variables);
            } finally {
                $this->queryTimer->stop(self::TIMING_KEY);
            }
        }

        return $result;
    }

    /**
     * Try to get result from cache
     *
     * @param  string     $query
     * @param  array|null $variables
     * @return array|null
     */
    private function tryGetFromCache(string $query, ?array $variables): ?array
    {
        $cacheKey = $this->cacheKeyGenerator->generate($query, $variables);
        $cachedResult = $this->cache->get($cacheKey);

        if ($cachedResult !== null) {
            $this->queryTimer->stop(self::TIMING_KEY, true);
            return [$query, $variables, $cachedResult];
        }

        return null;
    }

    /**
     * Optimize query
     *
     * @param  string     $query
     * @param  array|null $variables
     * @return array
     */
    private function optimizeQuery(string $query, ?array $variables): array
    {
        $optimizedQuery = $this->queryOptimizer->optimize($query);
        return [$optimizedQuery, $variables];
    }

    /**
     * Cache result if valid
     *
     * @param  array      $result
     * @param  string     $query
     * @param  array|null $variables
     * @return void
     */
    private function cacheResultIfValid(array $result, string $query, ?array $variables): void
    {
        if (!isset($result['errors'])) {
            $cacheKey = $this->cacheKeyGenerator->generate($query, $variables);
            $this->cache->set($cacheKey, $result, ['graphql_query'], self::CACHE_LIFETIME);
        }
    }
}
