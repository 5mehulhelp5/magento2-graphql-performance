<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Cart;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * GraphQL resolver for cart prices
 *
 * This resolver handles batch loading of cart totals and transforms them into
 * the GraphQL format. It provides detailed price information including taxes,
 * discounts, and shipping costs for multiple carts in a single operation.
 */
class PricesResolver implements BatchResolverInterface
{
    /**
     * @var array<int, ?\Magento\Quote\Api\Data\TotalsInterface> Cache of cart totals by cart ID
     */
    private array $totalsCache = [];

    /**
     * @param CartTotalRepositoryInterface $cartTotalRepository Cart total repository
     * @param PriceCurrencyInterface $priceCurrency Price currency service
     * @param StoreManagerInterface $storeManager Store manager
     */
    public function __construct(
        private readonly CartTotalRepositoryInterface $cartTotalRepository,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Batch resolve cart prices
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

        // Get all cart IDs
        $cartIds = array_map(
            function ($cart) {
                return $cart->getId();
            },
            $carts
        );

        // Load totals for all carts in batch
        $this->loadCartTotals($cartIds);

        // Get currency
        $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();

        foreach ($carts as $cart) {
            $cartId = $cart->getId();
            $totals = $this->totalsCache[$cartId] ?? null;

            if (!$totals) {
                continue;
            }

            $result[$cartId] = $this->transformPrices($totals, $currency);
        }

        return $result;
    }

    /**
     * Load cart totals in batch
     *
     * @param  array $cartIds
     * @return void
     */
    private function loadCartTotals(array $cartIds): void
    {
        $uncachedIds = array_diff($cartIds, array_keys($this->totalsCache));

        if (empty($uncachedIds)) {
            return;
        }

        foreach ($uncachedIds as $cartId) {
            try {
                $totals = $this->cartTotalRepository->get($cartId);
                $this->totalsCache[$cartId] = $totals;
            } catch (\Exception $e) {
                $this->totalsCache[$cartId] = null;
            }
        }
    }

    /**
     * Transform cart totals to prices format
     *
     * @param  \Magento\Quote\Api\Data\TotalsInterface $totals
     * @param  string                                  $currency
     * @return array
     */
    private function transformPrices($totals, string $currency): array
    {
        $subtotal = $totals->getSubtotal();
        $grandTotal = $totals->getGrandTotal();
        $discountAmount = abs($totals->getDiscountAmount());

        $prices = [
            'subtotal_excluding_tax' => [
                'value' => $this->formatPrice($subtotal),
                'currency' => $currency
            ],
            'subtotal_including_tax' => [
                'value' => $this->formatPrice($subtotal + $totals->getTaxAmount()),
                'currency' => $currency
            ],
            'grand_total' => [
                'value' => $this->formatPrice($grandTotal),
                'currency' => $currency
            ],
            'discounts' => []
        ];

        // Add discount information if available
        if ($discountAmount > 0) {
            $prices['discounts'][] = [
                'amount' => [
                    'value' => $this->formatPrice($discountAmount),
                    'currency' => $currency
                ],
                'label' => $totals->getDiscountDescription() ?: 'Discount'
            ];
        }

        // Add applied taxes
        $appliedTaxes = $totals->getAppliedTaxes() ?: [];
        $prices['applied_taxes'] = array_map(
            function ($tax) use ($currency) {
                return [
                'amount' => [
                    'value' => $this->formatPrice($tax['amount']),
                    'currency' => $currency
                ],
                'label' => $tax['title'],
                'rate' => $tax['percent']
                ];
            },
            $appliedTaxes
        );

        // Add shipping information if available
        if ($totals->getShippingAmount() > 0) {
            $prices['shipping_handling'] = [
                'amount_excluding_tax' => [
                    'value' => $this->formatPrice($totals->getShippingAmount()),
                    'currency' => $currency
                ],
                'amount_including_tax' => [
                    'value' => $this->formatPrice($totals->getShippingInclTax()),
                    'currency' => $currency
                ],
                'taxes' => array_map(
                    function ($tax) use ($currency) {
                        return [
                        'amount' => [
                            'value' => $this->formatPrice($tax['amount']),
                            'currency' => $currency
                        ],
                        'rate' => $tax['percent']
                        ];
                    },
                    $totals->getShippingTaxes() ?: []
                )
            ];
        }

        return $prices;
    }

    /**
     * Format price
     *
     * @param  float $price
     * @return float
     */
    private function formatPrice(float $price): float
    {
        return (float)$this->priceCurrency->round($price);
    }
}
