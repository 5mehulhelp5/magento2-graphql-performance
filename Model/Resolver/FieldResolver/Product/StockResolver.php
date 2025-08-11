<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class StockResolver implements BatchResolverInterface
{
    private array $stockCache = [];

    public function __construct(
        private readonly StockRegistryInterface $stockRegistry
    ) {
    }

    /**
     * Batch resolve product stock status
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
 * @var ProductInterface[] $products
*/
        $products = $value['products'] ?? [];
        $result = [];

        // Batch load stock data
        $stockData = $this->getStockData(
            array_map(
                function ($product) {
                    return $product->getId();
                },
                $products
            )
        );

        foreach ($products as $product) {
            $stockItem = $stockData[$product->getId()] ?? null;

            if (!$stockItem) {
                $result[$product->getId()] = 'OUT_OF_STOCK';
                continue;
            }

            $result[$product->getId()] = $this->getStockStatus(
                $stockItem,
                $product->getTypeId()
            );
        }

        return $result;
    }

    /**
     * Get stock data for multiple products
     *
     * @param  array $productIds
     * @return array
     */
    private function getStockData(array $productIds): array
    {
        $result = [];
        $uncachedIds = array_diff($productIds, array_keys($this->stockCache));

        if (!empty($uncachedIds)) {
            // Load stock data in batch
            foreach ($uncachedIds as $productId) {
                try {
                    $stockItem = $this->stockRegistry->getStockItem($productId);
                    $this->stockCache[$productId] = $stockItem;
                } catch (\Exception $e) {
                    $this->stockCache[$productId] = null;
                }
            }
        }

        foreach ($productIds as $productId) {
            $result[$productId] = $this->stockCache[$productId] ?? null;
        }

        return $result;
    }

    /**
     * Get stock status
     *
     * @param  \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem
     * @param  string                                                $typeId
     * @return string
     */
    private function getStockStatus($stockItem, string $typeId): string
    {
        if (!$stockItem->getIsInStock()) {
            return 'OUT_OF_STOCK';
        }

        // Handle different product types
        switch ($typeId) {
            case 'configurable':
            case 'grouped':
            case 'bundle':
                // For composite products, we might want to check child products
                // This is simplified for performance, but you can extend it
                return 'IN_STOCK';

            default:
                if ($stockItem->getQty() > $stockItem->getMinQty()) {
                    return 'IN_STOCK';
                }
                return 'OUT_OF_STOCK';
        }
    }
}
