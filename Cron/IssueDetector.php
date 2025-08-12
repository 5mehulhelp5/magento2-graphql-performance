<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Psr\Log\LoggerInterface;

/**
 * Service for detecting performance issues in GraphQL queries
 *
 * This class analyzes performance metrics and detects potential issues such as
 * slow queries, low cache hit rates, high memory usage, and connection pool
 * saturation. It logs warnings when issues are detected to help identify
 * performance bottlenecks.
 */
class IssueDetector
{
    private const SLOW_QUERY_THRESHOLD = 1.0; // seconds
    private const LOW_CACHE_HIT_RATE = 0.5; // 50%
    private const HIGH_MEMORY_USAGE = 256 * 1024 * 1024; // 256MB
    private const HIGH_CONNECTION_USAGE = 0.8; // 80%

    /**
     * @param LoggerInterface $logger Logger for recording detected issues
     */
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Check for performance issues in metrics
     *
     * @param array $metrics Performance metrics data
     */
    public function checkForIssues(array $metrics): void
    {
        $this->checkQueryPerformance($metrics['query_metrics'] ?? []);
        $this->checkCachePerformance($metrics['cache_stats'] ?? []);
        $this->checkConnectionPool($metrics['connection_pool_stats'] ?? []);
        $this->checkMemoryUsage($metrics['memory_usage'] ?? []);
    }

    /**
     * Check for slow queries
     *
     * @param array $queryMetrics Query performance metrics
     */
    private function checkQueryPerformance(array $queryMetrics): void
    {
        foreach ($queryMetrics as $query => $timing) {
            if ($timing > self::SLOW_QUERY_THRESHOLD) {
                $this->logger->warning(sprintf(
                    'Slow GraphQL query detected: %s (%.2f seconds)',
                    $query,
                    $timing
                ));
            }
        }
    }

    /**
     * Check cache performance
     *
     * @param array $cacheStats Cache statistics
     */
    private function checkCachePerformance(array $cacheStats): void
    {
        $hits = $cacheStats['hits'] ?? 0;
        $misses = $cacheStats['misses'] ?? 0;
        $total = $hits + $misses;

        if ($total > 0) {
            $hitRate = $hits / $total;
            if ($hitRate < self::LOW_CACHE_HIT_RATE) {
                $this->logger->warning(sprintf(
                    'Low cache hit rate: %.2f%% (hits: %d, misses: %d)',
                    $hitRate * 100,
                    $hits,
                    $misses
                ));
            }
        }
    }

    /**
     * Check connection pool usage
     *
     * @param array $poolStats Connection pool statistics
     */
    private function checkConnectionPool(array $poolStats): void
    {
        $active = $poolStats['active_connections'] ?? 0;
        $total = $poolStats['total_connections'] ?? 1;
        $usage = $active / $total;

        if ($usage > self::HIGH_CONNECTION_USAGE) {
            $this->logger->warning(sprintf(
                'High connection pool usage: %.2f%% (%d/%d connections)',
                $usage * 100,
                $active,
                $total
            ));
        }
    }

    /**
     * Check memory usage
     *
     * @param array $memoryStats Memory usage statistics
     */
    private function checkMemoryUsage(array $memoryStats): void
    {
        $current = $memoryStats['current'] ?? 0;
        $peak = $memoryStats['peak'] ?? 0;

        if ($current > self::HIGH_MEMORY_USAGE) {
            $this->logger->warning(sprintf(
                'High current memory usage: %.2f MB',
                $current / 1024 / 1024
            ));
        }

        if ($peak > self::HIGH_MEMORY_USAGE) {
            $this->logger->warning(sprintf(
                'High peak memory usage: %.2f MB',
                $peak / 1024 / 1024
            ));
        }
    }
}
