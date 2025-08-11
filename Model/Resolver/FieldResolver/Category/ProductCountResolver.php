<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Category;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status as StockStatusResource;

class ProductCountResolver implements BatchResolverInterface
{
    private array $countCache = [];

    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly StockStatusResource $stockStatusResource,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Batch resolve product count
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array       $value
     * @param  array       $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = [],
        array $args = []
    ): array {
        /**
 * @var \Magento\Catalog\Api\Data\CategoryInterface[] $categories
*/
        $categories = $value['categories'] ?? [];
        $result = [];

        // Get all category IDs
        $categoryIds = array_map(
            function ($category) {
                return $category->getId();
            },
            $categories
        );

        // Load product counts in batch
        $productCounts = $this->getProductCounts($categoryIds);

        foreach ($categories as $category) {
            $categoryId = $category->getId();
            $result[$categoryId] = $productCounts[$categoryId] ?? 0;
        }

        return $result;
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
