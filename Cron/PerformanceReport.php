<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionPool;
use Psr\Log\LoggerInterface;

class PerformanceReport
{
    public function __construct(
        private readonly QueryTimer $queryTimer,
        private readonly ResolverCache $cache,
        private readonly ConnectionPool $connectionPool,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(): void
    {
        try {
            $metrics = $this->queryTimer->getMetrics();
            $cacheStats = $this->cache->getStats();
            $poolStats = $this->connectionPool->getStats();

            $report = [
                'timestamp' => date('Y-m-d H:i:s'),
                'query_metrics' => $metrics,
                'cache_stats' => $cacheStats,
                'connection_pool_stats' => $poolStats,
                'memory_usage' => [
                    'current' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true)
                ]
            ];

            $this->logger->info('GraphQL Performance Report', $report);

            // Alert on potential issues
            $this->checkForIssues($report);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate GraphQL performance report: ' . $e->getMessage());
        }
    }

    private function checkForIssues(array $report): void
    {
        // Check cache hit rate
        if (($report['cache_stats']['hit_rate'] ?? 1) < 0.8) {
            $this->logger->warning('Low GraphQL cache hit rate detected', [
                'hit_rate' => $report['cache_stats']['hit_rate']
            ]);
        }

        // Check slow query rate
        $totalQueries = $report['query_metrics']['query_count'] ?? 0;
        if ($totalQueries > 0) {
            $slowQueryRate = ($report['query_metrics']['slow_queries'] ?? 0) / $totalQueries;
            if ($slowQueryRate > 0.1) {
                $this->logger->warning('High rate of slow GraphQL queries detected', [
                    'rate' => $slowQueryRate,
                    'threshold' => 0.1
                ]);
            }
        }

        // Check connection pool utilization
        $poolStats = $report['connection_pool_stats'] ?? [];
        if (isset($poolStats['total_connections'], $poolStats['max_connections'])) {
            $utilization = $poolStats['total_connections'] / $poolStats['max_connections'];
            if ($utilization > 0.9) {
                $this->logger->warning('High GraphQL connection pool utilization detected', [
                    'utilization' => $utilization,
                    'threshold' => 0.9
                ]);
            }
        }

        // Check memory usage
        $memoryUsage = $report['memory_usage']['current'] ?? 0;
        $memoryLimit = $this->getMemoryLimit();
        if ($memoryLimit > 0) {
            $memoryUtilization = $memoryUsage / $memoryLimit;
            if ($memoryUtilization > 0.8) {
                $this->logger->warning('High memory utilization detected', [
                    'utilization' => $memoryUtilization,
                    'threshold' => 0.8
                ]);
            }
        }
    }

    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if (!$limit) {
            return 0;
        }

        $value = (int)$limit;
        $unit = strtolower(substr($limit, -1));

        switch ($unit) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
