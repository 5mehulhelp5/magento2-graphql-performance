<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Cache service for GraphQL resolvers
 *
 * This class provides caching functionality for GraphQL resolver results,
 * handling data serialization, cache key generation, and cache tag management.
 * It helps improve performance by caching frequently accessed resolver data.
 */
class ResolverCache
{
    private const CACHE_LIFETIME = 3600; // 1 hour

    /**
     * @param CacheInterface $cache Cache storage service
     * @param SerializerInterface $serializer Data serializer
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Get cached data
     *
     * @param  string $key
     * @return mixed|null
     */
    public function get(string $key)
    {
        $data = $this->cache->load($this->getCacheKey($key));
        if ($data === false) {
            return null;
        }

        return $this->serializer->unserialize($data);
    }

    /**
     * Save data to cache
     *
     * @param  string   $key
     * @param  mixed    $data
     * @param  array    $tags
     * @param  int|null $lifetime
     * @return bool
     */
    public function set(string $key, $data, array $tags = [], ?int $lifetime = null): bool
    {
        $tags = array_merge([GraphQlCache::CACHE_TAG], $tags);
        return $this->cache->save(
            $this->serializer->serialize($data),
            $this->getCacheKey($key),
            $tags,
            $lifetime ?? self::CACHE_LIFETIME
        );
    }

    /**
     * Generate cache key
     *
     * @param  string $key
     * @return string
     */
    private function getCacheKey(string $key): string
    {
        return 'graphql_' . sha1($key);
    }
}
