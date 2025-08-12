<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Psr\Log\LoggerInterface;

/**
 * Cron job for cleaning expired GraphQL cache entries
 *
 * This cron job runs periodically to remove expired entries from the GraphQL
 * resolver cache, helping maintain optimal cache size and performance.
 */
class CacheCleanup extends AbstractCron
{
    /**
     * @param ResolverCache $cache Cache service for GraphQL resolvers
     * @param LoggerInterface $logger Logger for recording cron job execution
     */
    public function __construct(
        private readonly ResolverCache $cache,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Process cron job by cleaning expired cache entries
     */
    protected function process(): void
    {
        $this->cache->cleanExpired();
    }
}
