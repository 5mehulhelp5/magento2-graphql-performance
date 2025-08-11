<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;
use Magento\Framework\Serialize\SerializerInterface;

class GraphQlCache extends TagScope
{
    /**
     * Cache type code unique among all cache types
     */
    const TYPE_IDENTIFIER = 'graphql';

    /**
     * Cache tag used to distinguish the cache type from all other cache
     */
    const CACHE_TAG = 'GRAPHQL';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    /**
     * @var SerializerInterface
     */
    private readonly SerializerInterface $serializer;

    /**
     * @param FrontendPool $cacheFrontendPool
     * @param SerializerInterface $serializer
     */
    public function __construct(
        FrontendPool $cacheFrontendPool,
        SerializerInterface $serializer
    ) {
        parent::__construct(
            $cacheFrontendPool->get(self::TYPE_IDENTIFIER),
            self::CACHE_TAG
        );
        $this->serializer = $serializer;
    }

    /**
     * Save data to cache
     *
     * @param mixed $data
     * @param string $identifier
     * @param array $tags
     * @param int|null $lifeTime
     * @return bool
     */
    public function save($data, $identifier, array $tags = [], $lifeTime = null): bool
    {
        $serializedData = $this->serializer->serialize($data);
        return parent::save($serializedData, $identifier, $tags, $lifeTime);
    }

    /**
     * Load data from cache
     *
     * @param string $identifier
     * @return mixed
     */
    public function load($identifier)
    {
        $data = parent::load($identifier);
        if ($data === false) {
            return false;
        }
        return $this->serializer->unserialize($data);
    }
}
