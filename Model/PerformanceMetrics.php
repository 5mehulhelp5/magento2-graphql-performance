<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model;

use Sterk\GraphQlPerformance\Api\PerformanceMetricsInterface;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionPoolManager;

/**
 * Service class for collecting and aggregating GraphQL performance metrics
 *
 * This class gathers performance data from various components including query timing,
 * cache statistics, and connection pool usage to provide a comprehensive view of
 * GraphQL performance.
 */
class PerformanceMetrics implements PerformanceMetricsInterface
{
    /**
     * @param QueryTimer $queryTimer Service for measuring query execution time
     * @param ResolverCache $cache Cache service for GraphQL resolvers
     * @param ConnectionPoolManager $connectionPool Service for managing database connections
     */
    public function __construct(
        private readonly QueryTimer $queryTimer,
        private readonly ResolverCache $cache,
        private readonly ConnectionPoolManager $connectionPool
    ) {
    }

    /**
     * Get performance metrics
     *
     * @return array
     */
    public function getMetrics(): array
    {
        $queryMetrics = $this->queryTimer->getMetrics();
        $cacheStats = $this->cache->getStats();
        $connectionStats = $this->connectionPool->getStats();

        return [
            'query_count' => $queryMetrics['total_queries'] ?? 0,
            'average_response_time' => $queryMetrics['average_time'] ?? 0,
            'cache_hit_rate' => $cacheStats['hit_rate'] ?? 0,
            'error_rate' => $queryMetrics['error_rate'] ?? 0,
            'slow_queries' => $queryMetrics['slow_queries'] ?? 0,
            'memory_usage' => [
                'current_usage' => memory_get_usage(true),
                'peak_usage' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit')
            ],
            'cache_stats' => $cacheStats,
            'connection_pool_stats' => $connectionStats
        ];
    }
}
