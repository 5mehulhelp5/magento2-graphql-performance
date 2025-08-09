<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Psr\Log\LoggerInterface;

class CacheCleanup
{
    public function __construct(
        private readonly ResolverCache $cache,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(): void
    {
        try {
            $this->cache->cleanExpired();
            $this->logger->info('GraphQL cache cleanup completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('GraphQL cache cleanup failed: ' . $e->getMessage());
        }
    }
}
