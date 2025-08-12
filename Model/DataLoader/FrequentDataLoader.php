<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use Magento\Framework\ObjectManagerInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

/**
 * Abstract base class for frequently accessed data loaders
 *
 * This class extends BatchDataLoader to add caching capabilities for
 * frequently accessed data. It implements a two-level caching strategy:
 * in-memory cache for the current request and persistent cache for
 * subsequent requests.
 */
abstract class FrequentDataLoader extends BatchDataLoader
{
    /**
     * @var array In-memory cache of loaded data
     */
    private array $loadedData = [];

    /**
     * @var array Cache keys for loaded items
     */
    private array $cacheKeys = [];

    /**
     * @param ObjectManagerInterface $objectManager Object manager for lazy loading
     * @param ResolverCache $cache Cache service for resolver results
     * @param PromiseAdapter $promiseAdapter GraphQL promise adapter
     * @param int $cacheLifetime Cache lifetime in seconds
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        protected readonly ResolverCache $cache,
        protected readonly PromiseAdapter $promiseAdapter,
        protected readonly int $cacheLifetime = 3600
    ) {
        parent::__construct($objectManager);
    }

    /**
     * Load a single item by ID with caching
     *
     * This method first checks the cache for the requested item. If found,
     * returns a fulfilled promise with the cached data. Otherwise, queues
     * the item for batch loading and returns a deferred promise.
     *
     * @param string $id Item identifier
     * @return Promise Promise that resolves to the loaded item
     */
    public function load($id): Promise
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

    /**
     * Load multiple items in a batch with caching
     *
     * This method loads items from the database in a batch operation and
     * caches the results. It is called automatically when queued items
     * need to be loaded.
     *
     * @param array $ids Array of item identifiers
     * @return array Loaded items indexed by ID
     */
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
     * @param  array $ids
     * @return array
     */
    abstract protected function loadFromDatabase(array $ids): array;

    /**
     * Generate cache key for an ID
     *
     * @param  string $id
     * @return string
     */
    abstract protected function generateCacheKey(string $id): string;

    /**
     * Get cache tags for an item
     *
     * @param  mixed $item
     * @return array
     */
    abstract protected function getCacheTags(mixed $item): array;
}
