<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Order;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

/**
 * GraphQL resolver for credit memos
 *
 * This resolver handles batch loading of credit memos with support for caching.
 * It transforms credit memo data into the GraphQL format and handles related
 * entities like comments, totals, and discounts.
 */
class CreditMemosResolver implements BatchResolverInterface
{
    /**
     * @var array<int, array<\Magento\Sales\Api\Data\CreditmemoInterface>> Cache of credit memos by order ID
     */
    private array $creditMemoCache = [];

    /**
     * @param CreditmemoRepositoryInterface $creditMemoRepository Credit memo repository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder Search criteria builder
     * @param ResolverCache $cache Cache service
     */
    public function __construct(
        private readonly CreditmemoRepositoryInterface $creditMemoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ResolverCache $cache
    ) {
    }

    /**
     * Batch resolve credit memos
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

        // Load credit memos in batch
        $this->loadCreditMemos($orderIds, $storeId);

        foreach ($requests as $request) {
            $orderId = $request['value']['id'] ?? null;
            if (!$orderId || !isset($this->creditMemoCache[$orderId])) {
                $response->addResponse($request, []);
                continue;
            }

            $result = array_map(
                fn($creditMemo) => $this->transformCreditMemoData(
                    $creditMemo,
                    $request['info']->getFieldSelection()
                ),
                $this->creditMemoCache[$orderId]
            );

            $response->addResponse($request, $result);
        }

        return $response;
    }

    /**
     * Load credit memos in batch
     *
     * @param  array $orderIds
     * @param  int   $storeId
     * @return void
     */
    private function loadCreditMemos(array $orderIds, int $storeId): void
    {
        $uncachedIds = array_diff($orderIds, array_keys($this->creditMemoCache));
        if (empty($uncachedIds)) {
            return;
        }

        foreach ($uncachedIds as $orderId) {
            $cacheKey = $this->generateCacheKey($orderId, $storeId);
            $cachedData = $this->cache->get($cacheKey);
            if ($cachedData !== null) {
                $this->creditMemoCache[$orderId] = $cachedData;
                continue;
            }
        }

        // Load remaining uncached credit memos
        $remainingIds = array_diff(
            $uncachedIds,
            array_keys($this->creditMemoCache)
        );

        if (empty($remainingIds)) {
            return;
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', $remainingIds, 'in')
            ->create();

        $creditMemos = $this->creditMemoRepository->getList($searchCriteria)->getItems();

        // Group credit memos by order ID
        $groupedCreditMemos = [];
        foreach ($creditMemos as $creditMemo) {
            $orderId = $creditMemo->getOrderId();
            if (!isset($groupedCreditMemos[$orderId])) {
                $groupedCreditMemos[$orderId] = [];
            }
            $groupedCreditMemos[$orderId][] = $creditMemo;
        }

        // Cache credit memos by order
        foreach ($groupedCreditMemos as $orderId => $orderCreditMemos) {
            $this->creditMemoCache[$orderId] = $orderCreditMemos;
            $cacheKey = $this->generateCacheKey($orderId, $storeId);
            $this->cache->set(
                $cacheKey,
                $orderCreditMemos,
                ['sales_creditmemo', 'order_' . $orderId],
                3600 // 1 hour cache
            );
        }
    }

    /**
     * Transform credit memo data to GraphQL format
     *
     * @param  \Magento\Sales\Api\Data\CreditmemoInterface $creditMemo
     * @param  array                                       $fields
     * @return array
     */
    private function transformCreditMemoData($creditMemo, array $fields): array
    {
        $result = [
            'id' => $creditMemo->getEntityId(),
            'number' => $creditMemo->getIncrementId(),
            'created_at' => $creditMemo->getCreatedAt(),
            'order_number' => $creditMemo->getOrder()->getIncrementId(),
            'state' => $this->getCreditMemoState($creditMemo->getState()),
            'comments' => $this->getCreditMemoComments($creditMemo),
            'total' => [
                'grand_total' => [
                    'value' => $creditMemo->getGrandTotal(),
                    'currency' => $creditMemo->getOrderCurrencyCode()
                ],
                'subtotal' => [
                    'value' => $creditMemo->getSubtotal(),
                    'currency' => $creditMemo->getOrderCurrencyCode()
                ],
                'shipping_handling' => [
                    'amount_including_tax' => [
                        'value' => $creditMemo->getShippingInclTax(),
                        'currency' => $creditMemo->getOrderCurrencyCode()
                    ],
                    'amount_excluding_tax' => [
                        'value' => $creditMemo->getShippingAmount(),
                        'currency' => $creditMemo->getOrderCurrencyCode()
                    ]
                ],
                'tax' => [
                    'value' => $creditMemo->getTaxAmount(),
                    'currency' => $creditMemo->getOrderCurrencyCode()
                ],
                'adjustment' => [
                    'value' => $creditMemo->getAdjustment(),
                    'currency' => $creditMemo->getOrderCurrencyCode()
                ],
                'discounts' => $this->getCreditMemoDiscounts($creditMemo),
                '__typename' => 'CreditMemoTotal'
            ],
            '__typename' => 'CreditMemo'
        ];

        // Add items if requested
        if (isset($fields['items'])) {
            $result['items'] = []; // Will be resolved by CreditMemoItemsResolver
        }

        return $result;
    }

    /**
     * Get credit memo state label
     *
     * @param  int $state
     * @return string
     */
    private function getCreditMemoState(int $state): string
    {
        $states = [
            \Magento\Sales\Model\Order\Creditmemo::STATE_OPEN => 'OPEN',
            \Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED => 'REFUNDED',
            \Magento\Sales\Model\Order\Creditmemo::STATE_CANCELED => 'CANCELED'
        ];

        return $states[$state] ?? 'UNKNOWN';
    }

    /**
     * Get credit memo comments
     *
     * @param  \Magento\Sales\Api\Data\CreditmemoInterface $creditMemo
     * @return array
     */
    private function getCreditMemoComments($creditMemo): array
    {
        $comments = [];
        foreach ($creditMemo->getComments() as $comment) {
            $comments[] = [
                'message' => $comment->getComment(),
                'timestamp' => $comment->getCreatedAt(),
                '__typename' => 'CreditMemoCommentInterface'
            ];
        }

        return $comments;
    }

    /**
     * Get credit memo discounts
     *
     * @param  \Magento\Sales\Api\Data\CreditmemoInterface $creditMemo
     * @return array
     */
    private function getCreditMemoDiscounts($creditMemo): array
    {
        $discounts = [];

        if ($creditMemo->getDiscountAmount() != 0) {
            $discounts[] = [
                'amount' => [
                    'value' => abs($creditMemo->getDiscountAmount()),
                    'currency' => $creditMemo->getOrderCurrencyCode()
                ],
                'label' => $creditMemo->getDiscountDescription() ?: 'Discount'
            ];
        }

        return $discounts;
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
            'order_creditmemos_%d_store_%d',
            $orderId,
            $storeId
        );
    }
}
