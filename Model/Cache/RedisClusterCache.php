<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Redis Cluster implementation for GraphQL caching
 *
 * This class provides Redis Cluster support for better scalability and
 * high availability. It implements automatic sharding and failover.
 */
class RedisClusterCache extends TagScope
{
    public const TYPE_IDENTIFIER = 'graphql_redis_cluster';
    public const CACHE_TAG = 'GRAPHQL_REDIS_CLUSTER';

    private array $nodes;
    private array $options;
    private ?\RedisCluster $cluster = null;

    public function __construct(
        FrontendPool $cacheFrontendPool,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        array $nodes = [],
        array $options = []
    ) {
        parent::__construct(
            $cacheFrontendPool->get(self::TYPE_IDENTIFIER),
            self::CACHE_TAG
        );

        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->nodes = $nodes;
        $this->options = array_merge([
            'timeout' => 1.0,
            'read_timeout' => 1.0,
            'persistent' => true,
            'distribute' => true
        ], $options);

        $this->initializeCluster();
    }

    private function initializeCluster(): void
    {
        try {
            if (empty($this->nodes)) {
                throw new \RuntimeException('No Redis cluster nodes configured');
            }

            $this->cluster = new \RedisCluster(
                null,
                $this->nodes,
                $this->options['timeout'],
                $this->options['read_timeout'],
                $this->options['persistent'],
                null // Auth not handled here for security
            );

            if ($this->options['distribute']) {
                $this->cluster->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_DISTRIBUTE);
            }

            $this->logger->info('Redis cluster initialized', [
                'nodes' => count($this->nodes),
                'options' => $this->options
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize Redis cluster: ' . $e->getMessage(), [
                'exception' => $e,
                'nodes' => $this->nodes
            ]);
            throw $e;
        }
    }

    public function save($data, $identifier, array $tags = [], $lifetime = null): bool
    {
        try {
            if (!$this->cluster) {
                return false;
            }

            $serializedData = $this->serializer->serialize($data);
            $result = $this->cluster->set(
                $this->getPrefixedId($identifier),
                $serializedData,
                $lifetime ?? $this->getDefaultLifetime()
            );

            if ($result && !empty($tags)) {
                $this->saveTags($identifier, $tags);
            }

            return (bool)$result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to save to Redis cluster: ' . $e->getMessage(), [
                'identifier' => $identifier,
                'tags' => $tags
            ]);
            return false;
        }
    }

    public function load($identifier)
    {
        try {
            if (!$this->cluster) {
                return false;
            }

            $data = $this->cluster->get($this->getPrefixedId($identifier));
            if ($data === false) {
                return false;
            }

            return $this->serializer->unserialize($data);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load from Redis cluster: ' . $e->getMessage(), [
                'identifier' => $identifier
            ]);
            return false;
        }
    }

    public function remove($identifier): bool
    {
        try {
            if (!$this->cluster) {
                return false;
            }

            return (bool)$this->cluster->del($this->getPrefixedId($identifier));
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove from Redis cluster: ' . $e->getMessage(), [
                'identifier' => $identifier
            ]);
            return false;
        }
    }

    public function clean(array $tags = []): bool
    {
        try {
            if (!$this->cluster) {
                return false;
            }

            if (empty($tags)) {
                // Flush all cache if no tags specified
                return $this->cluster->flushAll();
            }

            // Remove cache entries by tags
            foreach ($tags as $tag) {
                $tagMembers = $this->cluster->sMembers($this->getTagKey($tag));
                if (!empty($tagMembers)) {
                    $this->cluster->del(...array_map([$this, 'getPrefixedId'], $tagMembers));
                    $this->cluster->del($this->getTagKey($tag));
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to clean Redis cluster: ' . $e->getMessage(), [
                'tags' => $tags
            ]);
            return false;
        }
    }

    private function saveTags(string $identifier, array $tags): void
    {
        foreach ($tags as $tag) {
            $this->cluster->sAdd($this->getTagKey($tag), $identifier);
        }
    }

    private function getPrefixedId(string $identifier): string
    {
        return 'graphql:' . $identifier;
    }

    private function getTagKey(string $tag): string
    {
        return 'graphql_tag:' . $tag;
    }

    private function getDefaultLifetime(): int
    {
        return 3600; // 1 hour default
    }
}
