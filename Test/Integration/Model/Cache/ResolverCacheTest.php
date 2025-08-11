<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Test\Integration\Model\Cache;

use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\Cache\TagManager;

class ResolverCacheTest extends TestCase
{
    /**
     * @var ResolverCache
     */
    private $cache;

    /**
     * @var TagManager
     */
    private $tagManager;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->cache = $objectManager->get(ResolverCache::class);
        $this->tagManager = $objectManager->get(TagManager::class);
    }

    public function testBasicCacheOperations(): void
    {
        $key = 'test_cache_key';
        $data = ['test' => 'data'];
        $tags = ['test_tag'];
        $lifetime = 3600;

        // Test set
        $result = $this->cache->set($key, $data, $tags, $lifetime);
        $this->assertTrue($result);

        // Test get
        $cachedData = $this->cache->get($key);
        $this->assertEquals($data, $cachedData);

        // Test remove
        $result = $this->cache->remove($key);
        $this->assertTrue($result);

        // Verify removal
        $cachedData = $this->cache->get($key);
        $this->assertNull($cachedData);
    }

    public function testCacheWithTags(): void
    {
        $key1 = 'test_key_1';
        $key2 = 'test_key_2';
        $data1 = ['test' => 'data1'];
        $data2 = ['test' => 'data2'];
        $tag = 'test_tag';

        // Set multiple cache entries with the same tag
        $this->cache->set($key1, $data1, [$tag]);
        $this->cache->set($key2, $data2, [$tag]);

        // Verify both entries are cached
        $this->assertEquals($data1, $this->cache->get($key1));
        $this->assertEquals($data2, $this->cache->get($key2));

        // Clean by tag
        $this->cache->clean([$tag]);

        // Verify both entries are removed
        $this->assertNull($this->cache->get($key1));
        $this->assertNull($this->cache->get($key2));
    }

    public function testCacheExpiration(): void
    {
        $key = 'test_expiring_key';
        $data = ['test' => 'data'];
        $lifetime = 1; // 1 second

        // Set cache with short lifetime
        $this->cache->set($key, $data, [], $lifetime);

        // Verify data is cached
        $this->assertEquals($data, $this->cache->get($key));

        // Wait for expiration
        sleep(2);

        // Verify data is expired
        $this->assertNull($this->cache->get($key));
    }

    public function testTagManagerIntegration(): void
    {
        $entityType = 'product';
        $entityId = '123';
        $tags = $this->tagManager->getEntityTags($entityType, $entityId);

        $key = 'test_product_cache';
        $data = ['product_data' => 'test'];

        // Set cache with entity tags
        $this->cache->set($key, $data, $tags);

        // Verify data is cached
        $this->assertEquals($data, $this->cache->get($key));

        // Clean cache by entity tags
        $this->cache->clean($tags);

        // Verify cache is cleared
        $this->assertNull($this->cache->get($key));
    }

    public function testMultipleTagsHandling(): void
    {
        $key = 'test_multiple_tags';
        $data = ['test' => 'data'];
        $tags = ['tag1', 'tag2', 'tag3'];

        // Set cache with multiple tags
        $this->cache->set($key, $data, $tags);

        // Verify data is cached
        $this->assertEquals($data, $this->cache->get($key));

        // Clean by one tag
        $this->cache->clean(['tag2']);

        // Verify cache is cleared
        $this->assertNull($this->cache->get($key));
    }

    public function testCachePrefixing(): void
    {
        $key = 'test_prefix_key';
        $data = ['test' => 'data'];

        // Set cache
        $this->cache->set($key, $data);

        // Verify data is cached with expected prefix
        $this->assertEquals($data, $this->cache->get($key));

        // Try to get data with wrong prefix (should return null)
        $wrongPrefixKey = 'wrong_prefix_' . $key;
        $this->assertNull($this->cache->get($wrongPrefixKey));
    }
}
