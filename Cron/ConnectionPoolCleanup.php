<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionPool;
use Psr\Log\LoggerInterface;

class ConnectionPoolCleanup extends AbstractCron
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function process(): void
    {
        $this->connectionPool->cleanup();
    }
}
