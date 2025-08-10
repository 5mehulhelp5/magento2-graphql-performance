<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Performance;

use Psr\Log\LoggerInterface;

class IssueDetector
{
    private const THRESHOLDS = [
        'cache_hit_rate' => 0.8,
        'slow_query_rate' => 0.1,
        'pool_utilization' => 0.9,
        'memory_utilization' => 0.8
    ];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Check for performance issues
     *
     * @param array $metrics
     * @return void
     */
    public function checkForIssues(array $metrics): void
    {
        $this->checkCacheHitRate($metrics);
        $this->checkSlowQueryRate($metrics);
        $this->checkConnectionPoolUtilization($metrics);
        $this->checkMemoryUtilization($metrics);
    }

    /**
     * Check cache hit rate
     *
     * @param array $metrics
     * @return void
     */
    private function checkCacheHitRate(array $metrics): void
    {
        $hitRate = $metrics['cache_stats']['hit_rate'] ?? 1;
        if ($hitRate < self::THRESHOLDS['cache_hit_rate']) {
            $this->logger->warning('Low GraphQL cache hit rate detected', [
                'hit_rate' => $hitRate,
                'threshold' => self::THRESHOLDS['cache_hit_rate']
            ]);
        }
    }

    /**
     * Check slow query rate
     *
     * @param array $metrics
     * @return void
     */
    private function checkSlowQueryRate(array $metrics): void
    {
        $totalQueries = $metrics['query_metrics']['query_count'] ?? 0;
        if ($totalQueries > 0) {
            $slowQueries = $metrics['query_metrics']['slow_queries'] ?? 0;
            $slowQueryRate = $slowQueries / $totalQueries;

            if ($slowQueryRate > self::THRESHOLDS['slow_query_rate']) {
                $this->logger->warning('High rate of slow GraphQL queries detected', [
                    'rate' => $slowQueryRate,
                    'threshold' => self::THRESHOLDS['slow_query_rate']
                ]);
            }
        }
    }

    /**
     * Check connection pool utilization
     *
     * @param array $metrics
     * @return void
     */
    private function checkConnectionPoolUtilization(array $metrics): void
    {
        $poolStats = $metrics['connection_pool_stats'] ?? [];
        if (isset($poolStats['total_connections'], $poolStats['max_connections'])) {
            $utilization = $poolStats['total_connections'] / $poolStats['max_connections'];

            if ($utilization > self::THRESHOLDS['pool_utilization']) {
                $this->logger->warning('High GraphQL connection pool utilization detected', [
                    'utilization' => $utilization,
                    'threshold' => self::THRESHOLDS['pool_utilization']
                ]);
            }
        }
    }

    /**
     * Check memory utilization
     *
     * @param array $metrics
     * @return void
     */
    private function checkMemoryUtilization(array $metrics): void
    {
        $memoryUsage = $metrics['memory_usage']['current'] ?? 0;
        $memoryLimit = $this->getMemoryLimit();

        if ($memoryLimit > 0) {
            $utilization = $memoryUsage / $memoryLimit;

            if ($utilization > self::THRESHOLDS['memory_utilization']) {
                $this->logger->warning('High memory utilization detected', [
                    'utilization' => $utilization,
                    'threshold' => self::THRESHOLDS['memory_utilization']
                ]);
            }
        }
    }

    /**
     * Get memory limit in bytes
     *
     * @return int
     */
    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if (!$limit) {
            return 0;
        }

        $value = (int)$limit;
        $unit = strtolower(substr($limit, -1));

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }
}
