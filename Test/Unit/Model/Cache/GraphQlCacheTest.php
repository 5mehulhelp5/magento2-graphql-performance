<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Test\Unit\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Sterk\GraphQlPerformance\Model\Cache\GraphQlCache;

class GraphQlCacheTest extends TestCase
{
    /**
     * @var FrontendPool|\PHPUnit\Framework\MockObject\MockObject
     */
    private $frontendPool;

    /**
     * @var FrontendInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $cacheFrontend;

    /**
     * @var SerializerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $serializer;

    /**
     * @var GraphQlCache
     */
    private $cache;

    protected function setUp(): void
    {
        $this->frontendPool = $this->createMock(FrontendPool::class);
        $this->cacheFrontend = $this->createMock(FrontendInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);

        $this->frontendPool->expects($this->once())
            ->method('get')
            ->with(GraphQlCache::TYPE_IDENTIFIER)
            ->willReturn($this->cacheFrontend);

        $this->cache = new GraphQlCache($this->frontendPool, $this->serializer);
    }

    public function testSave(): void
    {
        $data = ['test' => 'data'];
        $identifier = 'test_id';
        $tags = ['tag1', 'tag2', GraphQlCache::CACHE_TAG];
        $lifeTime = 3600;

        $this->cacheFrontend->expects($this->once())
            ->method('save')
            ->with(
                $this->isType('string'),
                $identifier,
                $this->callback(function ($param) use ($tags) {
                    return is_array($param) && empty(array_diff($param, $tags));
                }),
                $lifeTime
            )
            ->willReturn(true);

        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($data)
            ->willReturn('serialized_data');

        $result = $this->cache->save('serialized_data', $identifier, $tags, $lifeTime);
        $this->assertTrue($result);
    }

    public function testLoad(): void
    {
        $identifier = 'test_id';
        $data = ['test' => 'data'];
        $serializedData = 'serialized_data';

        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($data)
            ->willReturn($serializedData);

        $this->cacheFrontend->expects($this->once())
            ->method('load')
            ->with($identifier)
            ->willReturn($data);

        $result = $this->cache->load($identifier);
        $this->assertEquals($data, $result);
    }

    public function testRemove(): void
    {
        $identifier = 'test_id';

        $this->cacheFrontend->expects($this->once())
            ->method('remove')
            ->with($identifier)
            ->willReturn(true);

        $result = $this->cache->remove($identifier);
        $this->assertTrue($result);
    }

    public function testClean(): void
    {
        $tags = ['tag1', 'tag2'];

        $this->cacheFrontend->expects($this->once())
            ->method('clean')
            ->with(
                $this->callback(function ($param) use ($tags) {
                    return is_array($param) && empty(array_diff($param, $tags));
                })
            )
            ->willReturn(true);

        $result = $this->cache->clean($tags);
        $this->assertTrue($result);
    }
}

