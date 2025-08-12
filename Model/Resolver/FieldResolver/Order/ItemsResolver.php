<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Order;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\DataLoader\ProductDataLoader;

/**
 * GraphQL resolver for order items
 *
 * This resolver handles batch loading of order items with support for caching
 * and product data loading. It transforms order item data into the GraphQL
 * format and handles related entities like products and selected options.
 */
class ItemsResolver implements BatchResolverInterface
{
    /**
     * @var array<int, array<\Magento\Sales\Api\Data\OrderItemInterface>> Cache of order items by order ID
     */
    private array $itemCache = [];

    /**
     * @var array<int, \Magento\Catalog\Api\Data\ProductInterface> Cache of products by product ID
     */
    private array $productCache = [];

    /**
     * @param OrderItemRepositoryInterface $orderItemRepository Order item repository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder Search criteria builder
     * @param ResolverCache $cache Cache service
     * @param ProductDataLoader $productDataLoader Product data loader
     */
    public function __construct(
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ResolverCache $cache,
        private readonly ProductDataLoader $productDataLoader
    ) {
    }

    /**
     * Batch resolve order items
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

        $orderIds = array_map(
            function ($request) {
                return $request['value']['id'] ?? null;
            },
            $requests
        );
        $orderIds = array_filter($orderIds);

        if (empty($orderIds)) {
            foreach ($requests as $request) {
                $response->addResponse($request, []);
            }
            return $response;
        }

        $storeId = $context->getExtensionAttributes()->getStore()->getId();

        // Load items in batch
        $this->loadOrderItems($orderIds, $storeId);

        // Load associated products
        $this->loadProducts($this->itemCache);

        foreach ($requests as $request) {
            $orderId = $request['value']['id'] ?? null;
            if (!$orderId || !isset($this->itemCache[$orderId])) {
                $response->addResponse($request, []);
                continue;
            }

            $result = array_map(
                fn($orderItem) => $this->transformOrderItemData(
                    $orderItem,
                    $request['info']->getFieldSelection()
                ),
                $this->itemCache[$orderId]
            );

            $response->addResponse($request, $result);
        }

        return $response;
    }

    /**
     * Load order items in batch
     *
     * @param  array $orderIds
     * @param  int   $storeId
     * @return void
     */
    private function loadOrderItems(array $orderIds, int $storeId): void
    {
        $uncachedIds = array_diff($orderIds, array_keys($this->itemCache));
        if (empty($uncachedIds)) {
            return;
        }

        foreach ($uncachedIds as $orderId) {
            $cacheKey = $this->generateCacheKey($orderId, $storeId);
            $cachedData = $this->cache->get($cacheKey);
            if ($cachedData !== null) {
                $this->itemCache[$orderId] = $cachedData;
                continue;
            }
        }

        // Load remaining uncached items
        $remainingIds = array_diff(
            $uncachedIds,
            array_keys($this->itemCache)
        );

        if (empty($remainingIds)) {
            return;
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', $remainingIds, 'in')
            ->create();

        $items = $this->orderItemRepository->getList($searchCriteria)->getItems();

        // Group items by order ID
        $groupedItems = [];
        foreach ($items as $item) {
            $orderId = $item->getOrderId();
            if (!isset($groupedItems[$orderId])) {
                $groupedItems[$orderId] = [];
            }
            $groupedItems[$orderId][] = $item;
        }

        // Cache items by order
        foreach ($groupedItems as $orderId => $orderItems) {
            $this->itemCache[$orderId] = $orderItems;
            $cacheKey = $this->generateCacheKey($orderId, $storeId);
            $this->cache->set(
                $cacheKey,
                $orderItems,
                ['sales_order_item', 'order_' . $orderId],
                3600 // 1 hour cache
            );
        }
    }

    /**
     * Load products for order items
     *
     * @param  array $itemsByOrder
     * @return void
     */
    private function loadProducts(array $itemsByOrder): void
    {
        $productIds = [];
        foreach ($itemsByOrder as $orderItems) {
            foreach ($orderItems as $item) {
                $productIds[] = $item->getProductId();
            }
        }
        $productIds = array_unique(array_filter($productIds));

        if (empty($productIds)) {
            return;
        }

        $uncachedIds = array_diff($productIds, array_keys($this->productCache));
        if (empty($uncachedIds)) {
            return;
        }

        // Load products using DataLoader
        $products = $this->productDataLoader->loadMany($uncachedIds);
        foreach ($products as $product) {
            $this->productCache[$product->getId()] = $product;
        }
    }

    /**
     * Transform order item data to GraphQL format
     *
     * @param  \Magento\Sales\Api\Data\OrderItemInterface $item
     * @param  array                                      $fields
     * @return array
     */
    private function transformOrderItemData($item, array $fields): array
    {
        $result = [
            'id' => $item->getItemId(),
            'product_name' => $item->getName(),
            'product_sku' => $item->getSku(),
            'product_url_key' => $item->getProductUrlKey(),
            'product_type' => $item->getProductType(),
            'status' => $item->getStatus(),
            'quantity_ordered' => $item->getQtyOrdered(),
            'quantity_shipped' => $item->getQtyShipped(),
            'quantity_refunded' => $item->getQtyRefunded(),
            'quantity_canceled' => $item->getQtyCanceled(),
            'product_sale_price' => [
                'value' => $item->getPrice(),
                'currency' => $item->getOrder()->getOrderCurrencyCode()
            ],
            '__typename' => 'OrderItem'
        ];

        // Add product data if requested
        if (isset($fields['product']) && isset($this->productCache[$item->getProductId()])) {
            $product = $this->productCache[$item->getProductId()];
            $result['product'] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'sku' => $product->getSku(),
                'url_key' => $product->getUrlKey(),
                'thumbnail' => [
                    'url' => $product->getThumbnail()
                        ? $product->getMediaGalleryImages()->getFirstItem()->getUrl()
                        : null
                ],
                '__typename' => 'SimpleProduct', // Adjust based on product type
                'model' => $product // Pass model for nested resolvers
            ];
        }

        // Add selected options if available
        if ($item->getProductOptions()) {
            $result['selected_options'] = $this->getSelectedOptions($item);
        }

        return $result;
    }

    /**
     * Get selected options for order item
     *
     * @param  \Magento\Sales\Api\Data\OrderItemInterface $item
     * @return array
     */
    private function getSelectedOptions($item): array
    {
        $options = [];
        $productOptions = $item->getProductOptions();

        if (isset($productOptions['options'])) {
            foreach ($productOptions['options'] as $option) {
                $options[] = [
                    'label' => $option['label'],
                    'value' => $option['value'],
                    '__typename' => 'OrderItemOption'
                ];
            }
        }

        if (isset($productOptions['attributes_info'])) {
            foreach ($productOptions['attributes_info'] as $attribute) {
                $options[] = [
                    'label' => $attribute['label'],
                    'value' => $attribute['value'],
                    '__typename' => 'OrderItemOption'
                ];
            }
        }

        return $options;
    }

    /**
     * Generate cache key
     *
     * @param  int $orderId
     * @param  int $storeId
     * @return string
     */
    private function generateCacheKey(int $orderId, int $storeId): string
    {
        return sprintf(
            'order_items_%d_store_%d',
            $orderId,
            $storeId
        );
    }
}
