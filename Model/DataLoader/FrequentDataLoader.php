<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Magento\Framework\App\ResourceConnection;

abstract class FrequentDataLoader extends BatchDataLoader
{
    private array $loadedData = [];
    private array $cacheKeys = [];

    public function __construct(
        PromiseAdapter $promiseAdapter,
        protected readonly ResolverCache $cache,
        protected readonly ResourceConnection $resourceConnection,
        protected readonly int $cacheLifetime = 3600
    ) {
        parent::__construct($promiseAdapter);
    }

    public function load(string $id): Promise
    {
        $cacheKey = $this->generateCacheKey($id);
        $cachedData = $this->cache->get($cacheKey);

        if ($cachedData !== null) {
            $this->loadedData[$id] = $cachedData;
            return $this->promiseAdapter->createFulfilled($cachedData);
        }

        $this->cacheKeys[$id] = $cacheKey;
        return parent::load($id);
    }

    protected function batchLoad(array $ids): array
    {
        // Load data from database
        $data = $this->loadFromDatabase($ids);

        // Cache the loaded data
        foreach ($data as $id => $item) {
            if (isset($this->cacheKeys[$id])) {
                $this->cache->set(
                    $this->cacheKeys[$id],
                    $item,
                    $this->getCacheTags($item),
                    $this->cacheLifetime
                );
            }
        }

        return $data;
    }

    /**
     * Load data from database in batch
     *
     * @param array $ids
     * @return array
     */
    abstract protected function loadFromDatabase(array $ids): array;

    /**
     * Generate cache key for an ID
     *
     * @param string $id
     * @return string
     */
    abstract protected function generateCacheKey(string $id): string;

    /**
     * Get cache tags for an item
     *
     * @param mixed $item
     * @return array
     */
    abstract protected function getCacheTags(mixed $item): array;
}
