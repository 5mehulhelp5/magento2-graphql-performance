<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Checkout;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Psr\Log\LoggerInterface;

/**
 * GraphQL resolver for selected payment methods
 *
 * This resolver handles batch loading of selected payment methods with support
 * for payment method instances and additional data. It transforms payment data
 * into the GraphQL format and handles related entities like payment methods.
 */
class SelectedPaymentMethodResolver implements BatchResolverInterface
{
    /**
     * @var array<int, ?\Magento\Quote\Api\Data\CartInterface> Cache of carts by cart ID
     */
    private array $cartCache = [];

    /**
     * @var array<string, ?\Magento\Payment\Model\MethodInterface> Cache of payment method instances by method code
     */
    private array $methodInstanceCache = [];

    /**
     * @param CartRepositoryInterface $cartRepository Cart repository
     * @param PaymentHelper $paymentHelper Payment helper
     * @param LoggerInterface|null $logger Logger service
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PaymentHelper $paymentHelper,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Batch resolve selected payment methods
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
 * @var \Magento\Quote\Api\Data\CartInterface[] $carts
*/
        $carts = $value['carts'] ?? [];
        $result = [];

        if (empty($carts)) {
            return $result;
        }

        // Load all carts in batch
        $this->loadCarts(
            array_map(
                function ($cart) {
                    return $cart->getId();
                },
                $carts
            )
        );

        foreach ($carts as $cart) {
            $cartId = $cart->getId();
            $loadedCart = $this->cartCache[$cartId] ?? null;

            if (!$loadedCart || !$loadedCart->getPayment()) {
                $result[$cartId] = null;
                continue;
            }

            $result[$cartId] = $this->getSelectedPaymentMethodData($loadedCart->getPayment());
        }

        return $result;
    }

    /**
     * Load carts in batch
     *
     * @param  array $cartIds
     * @return void
     */
    private function loadCarts(array $cartIds): void
    {
        $uncachedIds = array_diff($cartIds, array_keys($this->cartCache));

        if (empty($uncachedIds)) {
            return;
        }

        try {
            $carts = $this->cartRepository->getList(
                $this->buildSearchCriteria(['entity_id' => ['in' => $uncachedIds]])
            )->getItems();

            foreach ($carts as $cart) {
                $this->cartCache[$cart->getId()] = $cart;
            }
        } catch (\Exception $e) {
            // Silently handle cart loading errors to avoid breaking the GraphQL response
            // Individual cart errors will be reflected in the result array
            $this->logger?->error('Error loading carts: ' . $e->getMessage(), [
                'cart_ids' => $uncachedIds,
                'exception' => $e
            ]);
        }
    }

    /**
     * Get selected payment method data
     *
     * @param  \Magento\Quote\Api\Data\PaymentInterface $payment
     * @return array|null
     */
    private function getSelectedPaymentMethodData($payment): ?array
    {
        $method = $payment->getMethod();
        if (!$method) {
            return null;
        }

        $methodInstance = $this->getPaymentMethodInstance($method);
        if (!$methodInstance) {
            return null;
        }

        $additionalData = $payment->getAdditionalData() ? json_decode($payment->getAdditionalData(), true) : [];

        return [
            'code' => $method,
            'title' => $methodInstance->getTitle(),
            'purchase_order_number' => $payment->getPoNumber(),
            'additional_data' => array_map(
                function ($key, $value) {
                    return [
                    'key' => $key,
                    'value' => $value
                    ];
                },
                array_keys($additionalData),
                $additionalData
            )
        ];
    }

    /**
     * Get payment method instance
     *
     * @param  string $method
     * @return \Magento\Payment\Model\MethodInterface|null
     */
    private function getPaymentMethodInstance(string $method)
    {
        if (!isset($this->methodInstanceCache[$method])) {
            try {
                $this->methodInstanceCache[$method] = $this->paymentHelper->getMethodInstance($method);
            } catch (\Exception $e) {
                $this->methodInstanceCache[$method] = null;
            }
        }

        return $this->methodInstanceCache[$method];
    }

    /**
     * Build search criteria
     *
     * @param  array $filters
     * @return \Magento\Framework\Api\SearchCriteria
     */
    private function buildSearchCriteria(array $filters): \Magento\Framework\Api\SearchCriteria
    {
        $searchCriteriaBuilder = $this->objectManager->create(\Magento\Framework\Api\SearchCriteriaBuilder::class);

        foreach ($filters as $field => $condition) {
            $searchCriteriaBuilder->addFilter($field, $condition['value'], $condition['condition_type']);
        }

        return $searchCriteriaBuilder->create();
    }
}
