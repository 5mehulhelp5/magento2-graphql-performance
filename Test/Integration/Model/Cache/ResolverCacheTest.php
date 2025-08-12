<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Test\Integration\Model\Cache;

use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

class ResolverCacheTest extends TestCase
{
    /**
     * @var ResolverCache
     */
    private $resolverCache;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->resolverCache = $objectManager->create(ResolverCache::class);
    }

    public function testCacheKeyGeneration(): void
    {
        $key = 'test_key';
        $value = ['test' => 'value'];
        $tags = ['test_tag'];

        $this->resolverCache->set($key, $value, $tags);
        $result = $this->resolverCache->get($key);

        $this->assertEquals($value, $result);
    }

    protected function tearDown(): void
    {
        $this->resolverCache->clean();
    }
}
