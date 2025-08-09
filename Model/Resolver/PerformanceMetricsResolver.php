<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionPool;

class PerformanceMetricsResolver implements ResolverInterface
{
    public function __construct(
        private readonly QueryTimer $queryTimer,
        private readonly ResolverCache $cache,
        private readonly ConnectionPool $connectionPool
    ) {}

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $metrics = $this->queryTimer->getMetrics();
        $cacheStats = $this->cache->getStats();
        $poolStats = $this->connectionPool->getStats();

        return [
            'query_count' => $metrics['query_count'] ?? 0,
            'average_response_time' => $metrics['average_response_time'] ?? 0.0,
            'cache_hit_rate' => $metrics['cache_hit_rate'] ?? 0.0,
            'error_rate' => $metrics['error_rate'] ?? 0.0,
            'slow_queries' => $metrics['slow_queries'] ?? 0,
            'memory_usage' => [
                'current_usage' => memory_get_usage(true) / 1024 / 1024, // MB
                'peak_usage' => memory_get_peak_usage(true) / 1024 / 1024, // MB
                'limit' => ini_get('memory_limit')
            ],
            'cache_stats' => [
                'hits' => $cacheStats['hits'] ?? 0,
                'misses' => $cacheStats['misses'] ?? 0,
                'hit_rate' => $cacheStats['hit_rate'] ?? 0.0,
                'entries' => $cacheStats['entries'] ?? 0,
                'memory_usage' => $cacheStats['memory_usage'] ?? 0.0
            ],
            'connection_pool_stats' => [
                'active_connections' => $poolStats['active_connections'] ?? 0,
                'idle_connections' => $poolStats['idle_connections'] ?? 0,
                'total_connections' => $poolStats['total_connections'] ?? 0,
                'max_connections' => $poolStats['max_connections'] ?? 0,
                'wait_count' => $poolStats['wait_count'] ?? 0
            ]
        ];
    }
}
