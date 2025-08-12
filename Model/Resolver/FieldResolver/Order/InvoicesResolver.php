<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Order;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

/**
 * GraphQL resolver for order invoices
 *
 * This resolver handles batch loading of invoice data with support for caching.
 * It transforms invoice data into the GraphQL format and handles related
 * entities like invoice items, comments, and totals.
 */
class InvoicesResolver implements BatchResolverInterface
{
    /**
     * @var array<int, array<\Magento\Sales\Api\Data\InvoiceInterface>> Cache of invoices by order ID
     */
    private array $invoiceCache = [];

    /**
     * @param InvoiceRepositoryInterface $invoiceRepository Invoice repository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder Search criteria builder
     * @param ResolverCache $cache Cache service
     */
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ResolverCache $cache
    ) {
    }

    /**
     * Batch resolve order invoices
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
 * @var array $orderIds
*/
        $orderIds = array_map(
            function ($item) {
                return $item['id'] ?? null;
            },
            $value
        );
        $orderIds = array_filter($orderIds);

        if (empty($orderIds)) {
            return [];
        }

        $storeId = $context->getExtensionAttributes()->getStore()->getId();

        // Load invoices in batch
        $this->loadInvoices($orderIds, $storeId);

        $result = [];
        foreach ($value as $index => $item) {
            $orderId = $item['id'] ?? null;
            if (!$orderId || !isset($this->invoiceCache[$orderId])) {
                $result[$index] = [];
                continue;
            }

            $result[$index] = array_map(
                fn($invoice) => $this->transformInvoiceData($invoice, $info->getFieldSelection()),
                $this->invoiceCache[$orderId]
            );
        }

        return $result;
    }

    /**
     * Load invoices in batch
     *
     * @param  array $orderIds
     * @param  int   $storeId
     * @return void
     */
    private function loadInvoices(array $orderIds, int $storeId): void
    {
        $uncachedIds = array_diff($orderIds, array_keys($this->invoiceCache));
        if (empty($uncachedIds)) {
            return;
        }

        foreach ($uncachedIds as $orderId) {
            $cacheKey = $this->generateCacheKey($orderId, $storeId);
            $cachedData = $this->cache->get($cacheKey);
            if ($cachedData !== null) {
                $this->invoiceCache[$orderId] = $cachedData;
                continue;
            }
        }

        // Load remaining uncached invoices
        $remainingIds = array_diff(
            $uncachedIds,
            array_keys($this->invoiceCache)
        );

        if (empty($remainingIds)) {
            return;
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', $remainingIds, 'in')
            ->create();

        $invoices = $this->invoiceRepository->getList($searchCriteria)->getItems();

        // Group invoices by order ID
        $groupedInvoices = [];
        foreach ($invoices as $invoice) {
            $orderId = $invoice->getOrderId();
            if (!isset($groupedInvoices[$orderId])) {
                $groupedInvoices[$orderId] = [];
            }
            $groupedInvoices[$orderId][] = $invoice;
        }

        // Cache invoices by order
        foreach ($groupedInvoices as $orderId => $orderInvoices) {
            $this->invoiceCache[$orderId] = $orderInvoices;
            $cacheKey = $this->generateCacheKey($orderId, $storeId);
            $this->cache->set(
                $cacheKey,
                $orderInvoices,
                ['sales_invoice', 'order_' . $orderId],
                3600 // 1 hour cache
            );
        }
    }

    /**
     * Transform invoice data to GraphQL format
     *
     * @param  \Magento\Sales\Api\Data\InvoiceInterface $invoice
     * @param  array                                    $fields
     * @return array
     */
    private function transformInvoiceData($invoice, array $fields): array
    {
        $result = [
            'id' => $invoice->getEntityId(),
            'number' => $invoice->getIncrementId(),
            'created_at' => $invoice->getCreatedAt(),
            'order_number' => $invoice->getOrder()->getIncrementId(),
            'state' => $this->getInvoiceState($invoice->getState()),
            'total' => [
                'grand_total' => [
                    'value' => $invoice->getGrandTotal(),
                    'currency' => $invoice->getOrderCurrencyCode()
                ],
                'subtotal' => [
                    'value' => $invoice->getSubtotal(),
                    'currency' => $invoice->getOrderCurrencyCode()
                ],
                'shipping_handling' => [
                    'amount_including_tax' => [
                        'value' => $invoice->getShippingInclTax(),
                        'currency' => $invoice->getOrderCurrencyCode()
                    ],
                    'amount_excluding_tax' => [
                        'value' => $invoice->getShippingAmount(),
                        'currency' => $invoice->getOrderCurrencyCode()
                    ]
                ],
                'tax' => [
                    'value' => $invoice->getTaxAmount(),
                    'currency' => $invoice->getOrderCurrencyCode()
                ],
                'discounts' => $this->getInvoiceDiscounts($invoice),
                '__typename' => 'InvoiceTotal'
            ],
            '__typename' => 'Invoice'
        ];

        // Add items if requested
        if (isset($fields['items'])) {
            $result['items'] = $this->getInvoiceItems($invoice);
        }

        // Add comments if requested
        if (isset($fields['comments'])) {
            $result['comments'] = $this->getInvoiceComments($invoice);
        }

        return $result;
    }

    /**
     * Get invoice state label
     *
     * @param  int $state
     * @return string
     */
    private function getInvoiceState(int $state): string
    {
        $states = [
            \Magento\Sales\Model\Order\Invoice::STATE_OPEN => 'OPEN',
            \Magento\Sales\Model\Order\Invoice::STATE_PAID => 'PAID',
            \Magento\Sales\Model\Order\Invoice::STATE_CANCELED => 'CANCELED'
        ];

        return $states[$state] ?? 'UNKNOWN';
    }

    /**
     * Get invoice discounts
     *
     * @param  \Magento\Sales\Api\Data\InvoiceInterface $invoice
     * @return array
     */
    private function getInvoiceDiscounts($invoice): array
    {
        $discounts = [];

        if ($invoice->getDiscountAmount() != 0) {
            $discounts[] = [
                'amount' => [
                    'value' => abs($invoice->getDiscountAmount()),
                    'currency' => $invoice->getOrderCurrencyCode()
                ],
                'label' => $invoice->getDiscountDescription() ?: 'Discount'
            ];
        }

        return $discounts;
    }

    /**
     * Get invoice items
     *
     * @param  \Magento\Sales\Api\Data\InvoiceInterface $invoice
     * @return array
     */
    private function getInvoiceItems($invoice): array
    {
        $items = [];
        foreach ($invoice->getItems() as $item) {
            $items[] = [
                'id' => $item->getEntityId(),
                'order_item' => [
                    'id' => $item->getOrderItemId(),
                    'product_name' => $item->getName(),
                    'product_sku' => $item->getSku(),
                    'product_url_key' => $item->getProductUrlKey(),
                    '__typename' => 'OrderItemInterface'
                ],
                'product_name' => $item->getName(),
                'product_sku' => $item->getSku(),
                'quantity_invoiced' => $item->getQty(),
                'discounts' => $this->getItemDiscounts($item),
                'row_total' => [
                    'value' => $item->getRowTotal(),
                    'currency' => $invoice->getOrderCurrencyCode()
                ],
                '__typename' => 'InvoiceItemInterface'
            ];
        }

        return $items;
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
     * Get invoice comments
     *
     * @param  \Magento\Sales\Api\Data\InvoiceInterface $invoice
     * @return array
     */
    private function getInvoiceComments($invoice): array
    {
        $comments = [];
        foreach ($invoice->getComments() as $comment) {
            $comments[] = [
                'message' => $comment->getComment(),
                'timestamp' => $comment->getCreatedAt(),
                '__typename' => 'InvoiceCommentInterface'
            ];
        }

        return $comments;
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
            'order_invoices_%d_store_%d',
            $orderId,
            $storeId
        );
    }
}
