<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model;

use Sterk\GraphQlPerformance\Api\CacheManagementInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\Cache\CacheWarmer;

/**
 * Service class for managing GraphQL cache operations
 *
 * This class provides functionality for cleaning and warming the GraphQL cache,
 * helping maintain optimal performance and data freshness.
 */
class CacheManagement implements CacheManagementInterface
{
    /**
     * @param ResolverCache $cache Cache service for GraphQL resolvers
     * @param CacheWarmer $cacheWarmer Service for warming up the cache
     */
    public function __construct(
        private readonly ResolverCache $cache,
        private readonly CacheWarmer $cacheWarmer
    ) {
    }

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
