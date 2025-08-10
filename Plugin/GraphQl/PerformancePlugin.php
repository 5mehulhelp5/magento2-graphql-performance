<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Sterk\GraphQlPerformance\Model\Performance\QueryOptimizer;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

class PerformancePlugin
{
    private const CACHE_LIFETIME = 3600;
    private const TIMING_KEY = 'query_processing';

    public function __construct(
        private readonly QueryOptimizer $queryOptimizer,
        private readonly QueryTimer $queryTimer,
        private readonly ResolverCache $cache,
        private readonly CacheKeyGenerator $cacheKeyGenerator
    ) {}

    /**
     * Optimize query before processing
     *
     * @param QueryProcessor $subject
     * @param string $query
     * @param array|null $variables
     * @return array
     */
    public function beforeProcess(
        QueryProcessor $subject,
        string $query,
        ?array $variables = null
    ): array {
        $this->queryTimer->start(self::TIMING_KEY);

        try {
            $result = $this->tryGetFromCache($query, $variables);
            if ($result !== null) {
                return $result;
            }

            return $this->optimizeQuery($query, $variables);
        } catch (\Exception $e) {
            return [$query, $variables];
        }
    }

    /**
     * Process result after query execution
     *
     * @param QueryProcessor $subject
     * @param array $result
     * @param string $query
     * @param array|null $variables
     * @return array
     */
    public function afterProcess(
        QueryProcessor $subject,
        array $result,
        string $query,
        ?array $variables = null
    ): array {
        try {
            $this->cacheResultIfValid($result, $query, $variables);
        } finally {
            $this->queryTimer->stop(self::TIMING_KEY);
        }

        return $result;
    }

    /**
     * Try to get result from cache
     *
     * @param string $query
     * @param array|null $variables
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
     * @param string $query
     * @param array|null $variables
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
     * @param array $result
     * @param string $query
     * @param array|null $variables
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
