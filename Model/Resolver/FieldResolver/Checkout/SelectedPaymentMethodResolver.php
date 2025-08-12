<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Checkout;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\Api\SearchCriteriaBuilder;
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
     * @param SearchCriteriaBuilder $searchCriteriaBuilder Search criteria builder
     * @param LoggerInterface|null $logger Logger service
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PaymentHelper $paymentHelper,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Batch resolve selected payment methods
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
            $carts = $value['carts'] ?? [];

            if (empty($carts)) {
                $response->addResponse($request, []);
                continue;
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

            $result = [];
            foreach ($carts as $cart) {
                $cartId = $cart->getId();
                $loadedCart = $this->cartCache[$cartId] ?? null;

                if (!$loadedCart || !$loadedCart->getPayment()) {
                    $result[$cartId] = null;
                    continue;
                }

                $result[$cartId] = $this->getSelectedPaymentMethodData($loadedCart->getPayment());
            }

            $response->addResponse($request, $result);
        }

        return $response;
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
            $this->searchCriteriaBuilder->addFilter('entity_id', $uncachedIds, 'in');
            $searchCriteria = $this->searchCriteriaBuilder->create();
            $carts = $this->cartRepository->getList($searchCriteria)->getItems();

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
}
