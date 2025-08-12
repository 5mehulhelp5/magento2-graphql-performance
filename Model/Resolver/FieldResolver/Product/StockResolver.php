<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Config\Element\Field;

/**
 * Batch resolver for product stock status
 *
 * This class provides efficient batch resolution of product stock status,
 * handling stock data caching and status determination based on product type.
 * It implements the batch resolver interface to optimize performance when
 * resolving stock status for multiple products.
 */
class StockResolver implements BatchResolverInterface
{
    /**
     * @var array<int, ?\Magento\CatalogInventory\Api\Data\StockItemInterface> Cache of stock items
     */
    private array $stockCache = [];

    /**
     * @param StockRegistryInterface $stockRegistry Stock registry service
     */
    public function __construct(
        private readonly StockRegistryInterface $stockRegistry
    ) {
    }

    /**
     * Batch resolve product stock status
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
            $products = $value['products'] ?? [];

            if (empty($products)) {
                $response->addResponse($request, []);
                continue;
            }

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

            $response->addResponse($request, $result);
        }

        return $response;
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
