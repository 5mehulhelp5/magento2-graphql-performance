<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Psr\Log\LoggerInterface;

class CacheCleanup extends AbstractCron
{
    public function __construct(
        private readonly ResolverCache $cache,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function process(): void
    {
        $this->cache->cleanExpired();
    }
}
