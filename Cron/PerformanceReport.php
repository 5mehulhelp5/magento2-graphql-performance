<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionPool;
use Psr\Log\LoggerInterface;

class PerformanceReport extends AbstractCron
{
    public function __construct(
        private readonly QueryTimer $queryTimer,
        private readonly ResolverCache $cache,
        private readonly ConnectionPool $connectionPool,
        private readonly IssueDetector $issueDetector,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function process(): void
    {
        $metrics = $this->collectMetrics();
        $this->logger->info('GraphQL Performance Report', $metrics);
        $this->issueDetector->checkForIssues($metrics);
    }

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
