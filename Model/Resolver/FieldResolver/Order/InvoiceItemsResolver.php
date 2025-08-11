<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Order;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\InvoiceItemRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\DataLoader\ProductDataLoader;

class InvoiceItemsResolver implements BatchResolverInterface
{
    private array $itemCache = [];
    private array $productCache = [];

    public function __construct(
        private readonly InvoiceItemRepositoryInterface $invoiceItemRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ResolverCache $cache,
        private readonly ProductDataLoader $productDataLoader
    ) {
    }

    /**
     * Batch resolve invoice items
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
 * @var array $invoiceIds
*/
        $invoiceIds = array_map(
            function ($item) {
                return $item['id'] ?? null;
            },
            $value
        );
        $invoiceIds = array_filter($invoiceIds);

        if (empty($invoiceIds)) {
            return [];
        }

        $storeId = $context->getExtensionAttributes()->getStore()->getId();

        // Load items in batch
        $this->loadInvoiceItems($invoiceIds, $storeId);

        // Load associated products
        $this->loadProducts($this->itemCache);

        $result = [];
        foreach ($value as $index => $item) {
            $invoiceId = $item['id'] ?? null;
            if (!$invoiceId || !isset($this->itemCache[$invoiceId])) {
                $result[$index] = [];
                continue;
            }

            $result[$index] = array_map(
                fn($invoiceItem) => $this->transformInvoiceItemData($invoiceItem, $info->getFieldSelection()),
                $this->itemCache[$invoiceId]
            );
        }

        return $result;
    }

    /**
     * Load invoice items in batch
     *
     * @param  array $invoiceIds
     * @param  int   $storeId
     * @return void
     */
    private function loadInvoiceItems(array $invoiceIds, int $storeId): void
    {
        $uncachedIds = array_diff($invoiceIds, array_keys($this->itemCache));
        if (empty($uncachedIds)) {
            return;
        }

        foreach ($uncachedIds as $invoiceId) {
            $cacheKey = $this->generateCacheKey($invoiceId, $storeId);
            $cachedData = $this->cache->get($cacheKey);
            if ($cachedData !== null) {
                $this->itemCache[$invoiceId] = $cachedData;
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
            ->addFilter('parent_id', $remainingIds, 'in')
            ->create();

        $items = $this->invoiceItemRepository->getList($searchCriteria)->getItems();

        // Group items by invoice ID
        $groupedItems = [];
        foreach ($items as $item) {
            $invoiceId = $item->getParentId();
            if (!isset($groupedItems[$invoiceId])) {
                $groupedItems[$invoiceId] = [];
            }
            $groupedItems[$invoiceId][] = $item;
        }

        // Cache items by invoice
        foreach ($groupedItems as $invoiceId => $invoiceItems) {
            $this->itemCache[$invoiceId] = $invoiceItems;
            $cacheKey = $this->generateCacheKey($invoiceId, $storeId);
            $this->cache->set(
                $cacheKey,
                $invoiceItems,
                ['sales_invoice_item', 'invoice_' . $invoiceId],
                3600 // 1 hour cache
            );
        }
    }

    /**
     * Load products for invoice items
     *
     * @param  array $itemsByInvoice
     * @return void
     */
    private function loadProducts(array $itemsByInvoice): void
    {
        $productIds = [];
        foreach ($itemsByInvoice as $invoiceItems) {
            foreach ($invoiceItems as $item) {
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
     * Transform invoice item data to GraphQL format
     *
     * @param  \Magento\Sales\Api\Data\InvoiceItemInterface $item
     * @param  array                                        $fields
     * @return array
     */
    private function transformInvoiceItemData($item, array $fields): array
    {
        $result = [
            'id' => $item->getEntityId(),
            'order_item' => [
                'id' => $item->getOrderItemId(),
                'product_name' => $item->getName(),
                'product_sku' => $item->getSku(),
                '__typename' => 'OrderItemInterface'
            ],
            'product_name' => $item->getName(),
            'product_sku' => $item->getSku(),
            'quantity_invoiced' => $item->getQty(),
            'discounts' => $this->getItemDiscounts($item),
            'row_total' => [
                'value' => $item->getRowTotal(),
                'currency' => $item->getOrderCurrencyCode()
            ],
            '__typename' => 'InvoiceItemInterface'
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

        return $result;
    }

    /**
     * Get invoice item discounts
     *
     * @param  \Magento\Sales\Api\Data\InvoiceItemInterface $item
     * @return array
     */
    private function getItemDiscounts($item): array
    {
        $discounts = [];

        if ($item->getDiscountAmount() > 0) {
            $discounts[] = [
                'amount' => [
                    'value' => $item->getDiscountAmount(),
                    'currency' => $item->getOrderCurrencyCode()
                ],
                'label' => $item->getDiscountDescription() ?: 'Discount'
            ];
        }

        return $discounts;
    }

    /**
     * Generate cache key
     *
     * @param  int $invoiceId
     * @param  int $storeId
     * @return string
     */
    private function generateCacheKey(int $invoiceId, int $storeId): string
    {
        return sprintf(
            'invoice_items_%d_store_%d',
            $invoiceId,
            $storeId
        );
    }
}
