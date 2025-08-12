<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Category;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status as StockStatusResource;

/**
 * GraphQL resolver for category product counts
 *
 * This resolver efficiently calculates the number of active, visible, and
 * in-stock products for multiple categories in a single batch operation.
 * It uses caching to improve performance for frequently accessed categories.
 */
class ProductCountResolver implements BatchResolverInterface
{
    /**
     * @var array<int, int> Cache of product counts by category ID
     */
    private array $countCache = [];

    /**
     * @param ProductCollectionFactory $productCollectionFactory Product collection factory
     * @param StockStatusResource $stockStatusResource Stock status resource
     * @param StoreManagerInterface $storeManager Store manager
     */
    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly StockStatusResource $stockStatusResource,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Batch resolve product count
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

        // Get all category IDs
        $categories = [];
        foreach ($requests as $request) {
            $categoryData = $request['value']['categories'] ?? [];
            foreach ($categoryData as $category) {
                $categories[] = $category;
            }
        }

        if (empty($categories)) {
            foreach ($requests as $request) {
                $response->addResponse($request, []);
            }
            return $response;
        }

        $categoryIds = array_map(
            function ($category) {
                return $category->getId();
            },
            $categories
        );

        // Load product counts in batch
        $productCounts = $this->getProductCounts($categoryIds);

        foreach ($requests as $request) {
            $categoryData = $request['value']['categories'] ?? [];
            $result = [];

            foreach ($categoryData as $category) {
                $categoryId = $category->getId();
                $result[$categoryId] = $productCounts[$categoryId] ?? 0;
            }

            $response->addResponse($request, $result);
        }

        return $response;
    }

    /**
     * Get product counts for multiple categories
     *
     * @param  array $categoryIds
     * @return array
     */
    private function getProductCounts(array $categoryIds): array
    {
        $result = [];
        $uncachedIds = array_diff($categoryIds, array_keys($this->countCache));

        if (!empty($uncachedIds)) {
            $collection = $this->productCollectionFactory->create();
            $collection->setStore($this->storeManager->getStore())
                ->addCategoriesFilter(['in' => $uncachedIds])
                ->addAttributeToFilter('status', 1) // Only enabled products
                ->addAttributeToFilter('visibility', ['neq' => 1]); // Exclude not visible individually

            // Join with stock status
            $this->stockStatusResource->addStockDataToCollection(
                $collection,
                true // Only in stock
            );

            // Get counts per category
            $connection = $collection->getConnection();
            $select = $connection->select()
                ->from(
                    ['cat_index' => $collection->getTable('catalog_category_product_index')],
                    ['category_id', 'product_count' => 'COUNT(DISTINCT product_id)']
                )
                ->where('category_id IN (?)', $uncachedIds)
                ->group('category_id');

            $counts = $connection->fetchPairs($select);

            // Cache the results
            foreach ($uncachedIds as $categoryId) {
                $this->countCache[$categoryId] = (int)($counts[$categoryId] ?? 0);
            }
        }

        foreach ($categoryIds as $categoryId) {
            $result[$categoryId] = $this->countCache[$categoryId] ?? 0;
        }

        return $result;
    }
}
