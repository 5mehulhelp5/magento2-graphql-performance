<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Order;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\CreditmemoItemRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\DataLoader\ProductDataLoader;

class CreditMemoItemsResolver implements BatchResolverInterface
{
    private array $itemCache = [];
    private array $productCache = [];

    public function __construct(
        private readonly CreditmemoItemRepositoryInterface $creditMemoItemRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ResolverCache $cache,
        private readonly ProductDataLoader $productDataLoader
    ) {
    }

    /**
     * Batch resolve credit memo items
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
 * @var array $creditMemoIds
*/
        $creditMemoIds = array_map(
            function ($item) {
                return $item['id'] ?? null;
            },
            $value
        );
        $creditMemoIds = array_filter($creditMemoIds);

        if (empty($creditMemoIds)) {
            return [];
        }

        $storeId = $context->getExtensionAttributes()->getStore()->getId();

        // Load items in batch
        $this->loadCreditMemoItems($creditMemoIds, $storeId);

        // Load associated products
        $this->loadProducts($this->itemCache);

        $result = [];
        foreach ($value as $index => $item) {
            $creditMemoId = $item['id'] ?? null;
            if (!$creditMemoId || !isset($this->itemCache[$creditMemoId])) {
                $result[$index] = [];
                continue;
            }

            $result[$index] = array_map(
                fn($creditMemoItem) => $this->transformCreditMemoItemData($creditMemoItem, $info->getFieldSelection()),
                $this->itemCache[$creditMemoId]
            );
        }

        return $result;
    }

    /**
     * Load credit memo items in batch
     *
     * @param  array $creditMemoIds
     * @param  int   $storeId
     * @return void
     */
    private function loadCreditMemoItems(array $creditMemoIds, int $storeId): void
    {
        $uncachedIds = array_diff($creditMemoIds, array_keys($this->itemCache));
        if (empty($uncachedIds)) {
            return;
        }

        foreach ($uncachedIds as $creditMemoId) {
            $cacheKey = $this->generateCacheKey($creditMemoId, $storeId);
            $cachedData = $this->cache->get($cacheKey);
            if ($cachedData !== null) {
                $this->itemCache[$creditMemoId] = $cachedData;
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

        $items = $this->creditMemoItemRepository->getList($searchCriteria)->getItems();

        // Group items by credit memo ID
        $groupedItems = [];
        foreach ($items as $item) {
            $creditMemoId = $item->getParentId();
            if (!isset($groupedItems[$creditMemoId])) {
                $groupedItems[$creditMemoId] = [];
            }
            $groupedItems[$creditMemoId][] = $item;
        }

        // Cache items by credit memo
        foreach ($groupedItems as $creditMemoId => $creditMemoItems) {
            $this->itemCache[$creditMemoId] = $creditMemoItems;
            $cacheKey = $this->generateCacheKey($creditMemoId, $storeId);
            $this->cache->set(
                $cacheKey,
                $creditMemoItems,
                ['sales_creditmemo_item', 'creditmemo_' . $creditMemoId],
                3600 // 1 hour cache
            );
        }
    }

    /**
     * Load products for credit memo items
     *
     * @param  array $itemsByMemo
     * @return void
     */
    private function loadProducts(array $itemsByMemo): void
    {
        $productIds = [];
        foreach ($itemsByMemo as $creditMemoItems) {
            foreach ($creditMemoItems as $item) {
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
     * Transform credit memo item data to GraphQL format
     *
     * @param  \Magento\Sales\Api\Data\CreditmemoItemInterface $item
     * @param  array                                           $fields
     * @return array
     */
    private function transformCreditMemoItemData($item, array $fields): array
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
            'quantity_refunded' => $item->getQty(),
            'row_total' => [
                'value' => $item->getRowTotal(),
                'currency' => $item->getOrderCurrencyCode()
            ],
            'tax_amount' => [
                'value' => $item->getTaxAmount(),
                'currency' => $item->getOrderCurrencyCode()
            ],
            'discounts' => $this->getItemDiscounts($item),
            '__typename' => 'CreditMemoItemInterface'
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
     * Get credit memo item discounts
     *
     * @param  \Magento\Sales\Api\Data\CreditmemoItemInterface $item
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
     * @param  int $creditMemoId
     * @param  int $storeId
     * @return string
     */
    private function generateCacheKey(int $creditMemoId, int $storeId): string
    {
        return sprintf(
            'creditmemo_items_%d_store_%d',
            $creditMemoId,
            $storeId
        );
    }
}
