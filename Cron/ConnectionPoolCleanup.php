<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionPool;
use Psr\Log\LoggerInterface;

/**
 * Cron job for cleaning up idle database connections
 *
 * This cron job periodically removes idle connections from the connection pool,
 * helping maintain optimal resource usage and prevent connection leaks.
 */
class ConnectionPoolCleanup extends AbstractCron
{
    /**
     * @param ConnectionPool $connectionPool Database connection pool
     * @param LoggerInterface $logger Logger for recording cron job execution
     */
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Process cron job by cleaning up idle connections
     */
    protected function process(): void
    {
        $this->connectionPool->cleanup();
    }
}
