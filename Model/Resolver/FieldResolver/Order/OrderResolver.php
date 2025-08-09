<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Order;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

class OrderResolver implements BatchResolverInterface
{
    private array $orderCache = [];

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ResolverCache $cache
    ) {}

    /**
     * Batch resolve orders
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array $value
     * @param array $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = [],
        array $args = []
    ): array {
        /** @var array $orderIds */
        $orderIds = array_map(function ($item) {
            return $item['order_id'] ?? null;
        }, $value);
        $orderIds = array_filter($orderIds);

        if (empty($orderIds)) {
            return [];
        }

        $storeId = $context->getExtensionAttributes()->getStore()->getId();
        $customerId = $context->getUserId();

        // Load orders in batch
        $this->loadOrders($orderIds, $storeId, $customerId);

        $result = [];
        foreach ($value as $index => $item) {
            $orderId = $item['order_id'] ?? null;
            if (!$orderId || !isset($this->orderCache[$orderId])) {
                $result[$index] = null;
                continue;
            }

            $result[$index] = $this->transformOrderData(
                $this->orderCache[$orderId],
                $info->getFieldSelection()
            );
        }

        return $result;
    }

    /**
     * Load orders in batch
     *
     * @param array $orderIds
     * @param int $storeId
     * @param int|null $customerId
     * @return void
     */
    private function loadOrders(array $orderIds, int $storeId, ?int $customerId): void
    {
        $uncachedIds = array_diff($orderIds, array_keys($this->orderCache));
        if (empty($uncachedIds)) {
            return;
        }

        foreach ($uncachedIds as $orderId) {
            $cacheKey = $this->generateCacheKey($orderId, $storeId, $customerId);
            $cachedData = $this->cache->get($cacheKey);
            if ($cachedData !== null) {
                $this->orderCache[$orderId] = $cachedData;
                continue;
            }
        }

        // Load remaining uncached orders
        $remainingIds = array_diff(
            $uncachedIds,
            array_keys($this->orderCache)
        );

        if (empty($remainingIds)) {
            return;
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $remainingIds, 'in')
            ->addFilter('store_id', $storeId)
            ->create();

        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        foreach ($orders as $order) {
            // Verify customer ownership
            if ($customerId && $order->getCustomerId() != $customerId) {
                continue;
            }

            $orderId = $order->getId();
            $this->orderCache[$orderId] = $order;

            // Cache the order data
            $cacheKey = $this->generateCacheKey($orderId, $storeId, $customerId);
            $this->cache->set(
                $cacheKey,
                $order,
                ['sales_order', 'customer_' . $customerId],
                3600 // 1 hour cache
            );
        }
    }

    /**
     * Transform order data to GraphQL format
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param array $fields
     * @return array
     */
    private function transformOrderData($order, array $fields): array
    {
        $result = [
            'id' => $order->getId(),
            'increment_id' => $order->getIncrementId(),
            'created_at' => $order->getCreatedAt(),
            'grand_total' => [
                'value' => $order->getGrandTotal(),
                'currency' => $order->getOrderCurrencyCode()
            ],
            'status' => $order->getStatus(),
            'number_of_items_shipped' => $order->getTotalQtyOrdered(),
            'order_number' => $order->getIncrementId(),
            '__typename' => 'CustomerOrder'
        ];

        // Add optional fields based on GraphQL query
        if (isset($fields['items'])) {
            $result['items'] = []; // Will be resolved by ItemsResolver
        }

        if (isset($fields['shipping_address'])) {
            $result['shipping_address'] = []; // Will be resolved by ShippingAddressResolver
        }

        if (isset($fields['billing_address'])) {
            $result['billing_address'] = []; // Will be resolved by BillingAddressResolver
        }

        if (isset($fields['payment_methods'])) {
            $result['payment_methods'] = []; // Will be resolved by PaymentMethodsResolver
        }

        if (isset($fields['shipments'])) {
            $result['shipments'] = []; // Will be resolved by ShipmentsResolver
        }

        if (isset($fields['total'])) {
            $result['total'] = $this->getOrderTotals($order);
        }

        if (isset($fields['invoices'])) {
            $result['invoices'] = []; // Will be resolved by InvoicesResolver
        }

        if (isset($fields['credit_memos'])) {
            $result['credit_memos'] = []; // Will be resolved by CreditMemosResolver
        }

        return $result;
    }

    /**
     * Get order totals
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return array
     */
    private function getOrderTotals($order): array
    {
        return [
            'subtotal' => [
                'value' => $order->getSubtotal(),
                'currency' => $order->getOrderCurrencyCode()
            ],
            'shipping_handling' => [
                'amount_including_tax' => [
                    'value' => $order->getShippingInclTax(),
                    'currency' => $order->getOrderCurrencyCode()
                ],
                'amount_excluding_tax' => [
                    'value' => $order->getShippingAmount(),
                    'currency' => $order->getOrderCurrencyCode()
                ],
                'total_amount' => [
                    'value' => $order->getShippingAmount(),
                    'currency' => $order->getOrderCurrencyCode()
                ],
                'taxes' => [
                    [
                        'amount' => [
                            'value' => $order->getShippingTaxAmount(),
                            'currency' => $order->getOrderCurrencyCode()
                        ],
                        'title' => 'Shipping Tax',
                        'rate' => $this->calculateTaxRate(
                            $order->getShippingTaxAmount(),
                            $order->getShippingAmount()
                        )
                    ]
                ]
            ],
            'grand_total' => [
                'value' => $order->getGrandTotal(),
                'currency' => $order->getOrderCurrencyCode()
            ],
            'total_tax' => [
                'value' => $order->getTaxAmount(),
                'currency' => $order->getOrderCurrencyCode()
            ],
            'total_shipping' => [
                'value' => $order->getShippingAmount(),
                'currency' => $order->getOrderCurrencyCode()
            ],
            'discounts' => $this->getOrderDiscounts($order),
            '__typename' => 'OrderTotal'
        ];
    }

    /**
     * Get order discounts
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return array
     */
    private function getOrderDiscounts($order): array
    {
        $discounts = [];

        if ($order->getDiscountAmount() != 0) {
            $discounts[] = [
                'amount' => [
                    'value' => abs($order->getDiscountAmount()),
                    'currency' => $order->getOrderCurrencyCode()
                ],
                'label' => $order->getDiscountDescription() ?: 'Discount'
            ];
        }

        return $discounts;
    }

    /**
     * Calculate tax rate
     *
     * @param float $taxAmount
     * @param float $baseAmount
     * @return float
     */
    private function calculateTaxRate(float $taxAmount, float $baseAmount): float
    {
        if ($baseAmount > 0) {
            return round(($taxAmount / $baseAmount) * 100, 2);
        }
        return 0;
    }

    /**
     * Generate cache key
     *
     * @param int $orderId
     * @param int $storeId
     * @param int|null $customerId
     * @return string
     */
    private function generateCacheKey(int $orderId, int $storeId, ?int $customerId): string
    {
        return sprintf(
            'order_%d_store_%d_customer_%d',
            $orderId,
            $storeId,
            $customerId ?? 0
        );
    }
}
