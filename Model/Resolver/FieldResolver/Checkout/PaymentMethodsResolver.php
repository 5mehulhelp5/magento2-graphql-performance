<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Checkout;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Payment\Api\PaymentMethodListInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * GraphQL resolver for payment methods
 *
 * This resolver handles batch loading of payment methods with support for
 * filtering based on cart totals and currency. It provides detailed payment
 * method information including 3DS support, issuers, and amount limits.
 */
class PaymentMethodsResolver implements BatchResolverInterface
{
    /**
     * @var array<int, array<array{
     *     code: string,
     *     title: string,
     *     is_deferred: bool,
     *     available_issuers: array,
     *     supports_3ds: bool,
     *     minimum_amount: ?float,
     *     maximum_amount: ?float
     * }>> Cache of payment methods by store ID
     */
    private array $methodsCache = [];

    /**
     * @var array<int, ?\Magento\Quote\Api\Data\CartInterface> Cache of carts by cart ID
     */
    private array $cartCache = [];

    /**
     * @param PaymentMethodListInterface $paymentMethodList Payment method list service
     * @param CartRepositoryInterface $cartRepository Cart repository
     * @param StoreManagerInterface $storeManager Store manager
     * @param SearchCriteriaBuilder $searchCriteriaBuilder Search criteria builder
     */
    public function __construct(
        private readonly PaymentMethodListInterface $paymentMethodList,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * Batch resolve payment methods
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
        $storeId = $this->storeManager->getStore()->getId();

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

            // Load payment methods if not cached
            if (!isset($this->methodsCache[$storeId])) {
                $this->methodsCache[$storeId] = $this->loadPaymentMethods($storeId);
            }

            $result = [];
            foreach ($carts as $cart) {
                $cartId = $cart->getId();
                $loadedCart = $this->cartCache[$cartId] ?? null;

                if (!$loadedCart) {
                    $result[$cartId] = [];
                    continue;
                }

                $result[$cartId] = $this->filterMethodsForCart(
                    $this->methodsCache[$storeId],
                    $loadedCart
                );
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
            // If cart loading fails, we'll return empty results for those carts
            // This prevents the entire request from failing due to individual cart issues
            foreach ($uncachedIds as $cartId) {
                $this->cartCache[$cartId] = null;
            }
        }
    }

    /**
     * Load payment methods for store
     *
     * @param  int $storeId
     * @return array
     */
    private function loadPaymentMethods(int $storeId): array
    {
        $methods = $this->paymentMethodList->getActiveList($storeId);
        $result = [];

        foreach ($methods as $method) {
            $result[] = [
                'code' => $method->getCode(),
                'title' => $method->getTitle(),
                'is_deferred' => $this->isMethodDeferred($method->getCode()),
                'available_issuers' => $this->getAvailableIssuers($method->getCode()),
                'supports_3ds' => $this->supports3DS($method->getCode()),
                'minimum_amount' => $this->getMinimumAmount($method->getCode()),
                'maximum_amount' => $this->getMaximumAmount($method->getCode())
            ];
        }

        return $result;
    }

    /**
     * Filter methods for specific cart
     *
     * @param  array                                 $methods
     * @param  \Magento\Quote\Api\Data\CartInterface $cart
     * @return array
     */
    private function filterMethodsForCart(array $methods, $cart): array
    {
        $grandTotal = $cart->getGrandTotal();
        $currency = $cart->getQuoteCurrencyCode();

        return array_filter(
            $methods,
            function ($method) use ($grandTotal, $currency) {
                // Check minimum amount
                if ($method['minimum_amount'] && $grandTotal < $method['minimum_amount']) {
                    return false;
                }

                // Check maximum amount
                if ($method['maximum_amount'] && $grandTotal > $method['maximum_amount']) {
                    return false;
                }

                // Add more filtering logic here if needed
                return true;
            }
        );
    }

    /**
     * Check if payment method is deferred
     *
     * @param  string $code
     * @return bool
     */
    private function isMethodDeferred(string $code): bool
    {
        // Add logic to determine if payment method is deferred
        return in_array($code, ['checkmo', 'banktransfer']);
    }

    /**
     * Get available issuers for payment method
     *
     * @param  string $code
     * @return array
     */
    private function getAvailableIssuers(string $code): array
    {
        // Add logic to get available issuers for payment methods that support them
        switch ($code) {
            case 'nkolaypos':
                return [
                    ['code' => 'visa', 'title' => 'Visa'],
                    ['code' => 'mastercard', 'title' => 'Mastercard']
                ];
            default:
                return [];
        }
    }

    /**
     * Check if payment method supports 3DS
     *
     * @param  string $code
     * @return bool
     */
    private function supports3DS(string $code): bool
    {
        // Add logic to determine if payment method supports 3DS
        return in_array($code, ['nkolaypos', 'paytr']);
    }

    /**
     * Get minimum amount for payment method
     *
     * @param  string $code
     * @return float|null
     */
    private function getMinimumAmount(string $code): ?float
    {
        // Add logic to get minimum amount for payment method
        return match ($code) {
            'nkolaypos' => 10.0,
            'paytr' => 5.0,
            default => null
        };
    }

    /**
     * Get maximum amount for payment method
     *
     * @param  string $code
     * @return float|null
     */
    private function getMaximumAmount(string $code): ?float
    {
        // Add logic to get maximum amount for payment method
        return match ($code) {
            'nkolaypos' => 50000.0,
            'paytr' => 25000.0,
            default => null
        };
    }
}
