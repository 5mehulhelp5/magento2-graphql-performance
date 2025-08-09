<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionPool;
use Psr\Log\LoggerInterface;

class ConnectionPoolCleanup
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(): void
    {
        try {
            $this->connectionPool->cleanup();
            $this->logger->info('GraphQL connection pool cleanup completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('GraphQL connection pool cleanup failed: ' . $e->getMessage());
        }
    }
}
