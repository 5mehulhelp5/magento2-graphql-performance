<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionPool;
use Psr\Log\LoggerInterface;

/**
 * Cron job for generating GraphQL performance reports
 *
 * This cron job collects performance metrics from various components and generates
 * a comprehensive report, including query timing, cache statistics, connection pool
 * usage, and memory consumption. It also checks for potential performance issues.
 */
class PerformanceReport extends AbstractCron
{
    /**
     * @param QueryTimer $queryTimer Service for measuring query execution time
     * @param ResolverCache $cache Cache service for GraphQL resolvers
     * @param ConnectionPool $connectionPool Database connection pool
     * @param IssueDetector $issueDetector Service for detecting performance issues
     * @param LoggerInterface $logger Logger for recording cron job execution
     */
    public function __construct(
        private readonly QueryTimer $queryTimer,
        private readonly ResolverCache $cache,
        private readonly ConnectionPool $connectionPool,
        private readonly IssueDetector $issueDetector,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Process cron job by collecting metrics and generating report
     */
    protected function process(): void
    {
        $metrics = $this->collectMetrics();
        $this->logger->info('GraphQL Performance Report', $metrics);
        $this->issueDetector->checkForIssues($metrics);
    }

    /**
     * Collect performance metrics from various components
     *
     * @return array Performance metrics data
     */
    private function collectMetrics(): array
    {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'query_metrics' => $this->queryTimer->getMetrics(),
            'cache_stats' => $this->cache->getStats(),
            'connection_pool_stats' => $this->connectionPool->getStats(),
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true)
            ]
        ];
    }
}
