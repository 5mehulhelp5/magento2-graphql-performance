<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model;

use Sterk\GraphQlPerformance\Api\CacheManagementInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\Cache\CacheWarmer;

class CacheManagement implements CacheManagementInterface
{
    public function __construct(
        private readonly ResolverCache $cache,
        private readonly CacheWarmer $cacheWarmer
    ) {}

    /**
     * Clean GraphQL cache
     *
     * @return bool
     */
    public function clean(): bool
    {
        return $this->cache->clean(['graphql']);
    }

    /**
     * Warm GraphQL cache
     *
     * @return bool
     */
    public function warm(): bool
    {
        return $this->cacheWarmer->warm();
    }
}
