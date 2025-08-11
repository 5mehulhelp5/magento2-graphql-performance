<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use Magento\Framework\ObjectManagerInterface;

abstract class BatchDataLoader
{
    private array $queue = [];
    private array $loadedItems = [];
    private bool $loading = false;

    public function __construct(
        protected readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * Load a single item by ID
     *
     * @param  string|int $id
     * @return mixed
     */
    public function load($id)
    {
        // If already loaded, return from cache
        if (isset($this->loadedItems[$id])) {
            return $this->loadedItems[$id];
        }

        // Add to queue
        $this->queue[$id] = $id;

        // If we're not already loading, trigger a load
        if (!$this->loading) {
            $this->loading = true;
            $this->loadQueue();
            $this->loading = false;
        }

        return $this->loadedItems[$id] ?? null;
    }

    /**
     * Load multiple items by IDs
     *
     * @param  array $ids
     * @return array
     */
    public function loadMany(array $ids): array
    {
        // Add all IDs to queue
        foreach ($ids as $id) {
            if (!isset($this->loadedItems[$id])) {
                $this->queue[$id] = $id;
            }
        }

        // Load all queued items at once
        if (!empty($this->queue) && !$this->loading) {
            $this->loading = true;
            $this->loadQueue();
            $this->loading = false;
        }

        // Return requested items
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $this->loadedItems[$id] ?? null;
        }
        return $result;
    }

    /**
     * Process the queue and load items in batch
     */
    private function loadQueue(): void
    {
        if (empty($this->queue)) {
            return;
        }

        // Get unique IDs from queue
        $ids = array_values(array_unique($this->queue));

        // Clear queue
        $this->queue = [];

        // Load items in batch
        $items = $this->batchLoad($ids);

        // Cache loaded items
        foreach ($items as $id => $item) {
            $this->loadedItems[$id] = $item;
        }
    }

    /**
     * Clear the loaded items cache
     */
    public function clearCache(): void
    {
        $this->loadedItems = [];
    }

    /**
     * Batch load items - to be implemented by specific loaders
     *
     * @param  array $ids
     * @return array
     */
    abstract protected function batchLoad(array $ids): array;
}
