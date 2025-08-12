<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\Categories;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

/**
 * Abstract base class for category resolvers
 *
 * This class provides common functionality for category resolvers including
 * batch loading of categories and caching support. It implements the batch
 * resolver interface to optimize performance when resolving data for multiple
 * categories in a single operation.
 */
abstract class AbstractResolver implements BatchResolverInterface
{
    /**
     * @var array<int, \Magento\Catalog\Model\Category> Cache of categories by ID
     */
    protected array $categoryCache = [];

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory Category collection factory
     * @param StoreManagerInterface $storeManager Store manager
     * @param ResolverCache $cache Cache service
     */
    public function __construct(
        protected readonly CategoryCollectionFactory $categoryCollectionFactory,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly ResolverCache $cache
    ) {
    }

    /**
     * Batch resolve categories
     *
     * @param ContextInterface $context
     * @param Field $field
     * @param array $requests
     * @return BatchResponse
     */
    public function resolve(
        ContextInterface $context,
        Field $field,
        array $requests
    ): BatchResponse {
        $response = new BatchResponse();

        foreach ($requests as $request) {
            $value = $request['value'] ?? [];
            $categoryIds = $this->getCategoryIds($value);

            if (empty($categoryIds)) {
                $response->addResponse($request, []);
                continue;
            }

            // Load categories in batch
            $this->loadCategories($categoryIds);

            $result = $this->processCategories(
                $categoryIds,
                $request['info']->getFieldSelection()
            );

            $response->addResponse($request, $result);
        }

        return $response;
    }

    /**
     * Get category IDs from request value
     *
     * @param array $value
     * @return array
     */
    abstract protected function getCategoryIds(array $value): array;

    /**
     * Process categories and return result
     *
     * @param array $categoryIds
     * @param array $fields
     * @return array
     */
    abstract protected function processCategories(array $categoryIds, array $fields): array;

    /**
     * Load categories in batch
     *
     * @param array $categoryIds
     * @return void
     */
    protected function loadCategories(array $categoryIds): void
    {
        $uncachedIds = array_diff($categoryIds, array_keys($this->categoryCache));
        if (empty($uncachedIds)) {
            return;
        }

        $storeId = $this->storeManager->getStore()->getId();

        foreach ($uncachedIds as $categoryId) {
            $cacheKey = $this->generateCacheKey($categoryId, $storeId);
            $cachedData = $this->cache->get($cacheKey);
            if ($cachedData !== null) {
                $this->categoryCache[$categoryId] = $cachedData;
                continue;
            }
        }

        // Load remaining uncached categories
        $remainingIds = array_diff(
            $uncachedIds,
            array_keys($this->categoryCache)
        );

        if (empty($remainingIds)) {
            return;
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('*')
            ->addFieldToFilter('entity_id', ['in' => $remainingIds])
            ->setStore($storeId);

        foreach ($collection as $category) {
            $categoryId = $category->getId();
            $this->categoryCache[$categoryId] = $category;

            $cacheKey = $this->generateCacheKey($categoryId, $storeId);
            $this->cache->set(
                $cacheKey,
                $category,
                ['catalog_category', 'category_' . $categoryId],
                3600 // 1 hour cache
            );
        }
    }

    /**
     * Generate cache key
     *
     * @param int $categoryId
     * @param int $storeId
     * @return string
     */
    protected function generateCacheKey(int $categoryId, int $storeId): string
    {
        return sprintf(
            'category_%d_store_%d',
            $categoryId,
            $storeId
        );
    }
}
