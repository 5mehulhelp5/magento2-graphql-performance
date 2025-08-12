<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Batch resolver for product prices
 *
 * This class provides efficient batch resolution of product prices,
 * handling price calculations, discounts, and currency formatting.
 * It implements the batch resolver interface to optimize performance
 * when resolving prices for multiple products.
 */
class PriceResolver implements BatchResolverInterface
{
    /**
     * @param PriceCurrencyInterface $priceCurrency Price currency service
     * @param StoreManagerInterface $storeManager Store manager service
     */
    public function __construct(
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Batch resolve product prices
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
        $store = $this->storeManager->getStore();
        $currency = $store->getCurrentCurrencyCode();

        foreach ($requests as $request) {
            $value = $request['value'] ?? [];
            $products = $value['products'] ?? [];

            $result = [];
            foreach ($products as $product) {
                $regularPrice = $product->getPrice();
                $finalPrice = $this->getFinalPrice($product);
                $discount = $regularPrice - $finalPrice;

                $result[$product->getId()] = [
                    'maximum_price' => [
                        'regular_price' => [
                            'value' => $this->formatPrice($regularPrice),
                            'currency' => $currency
                        ],
                        'final_price' => [
                            'value' => $this->formatPrice($finalPrice),
                            'currency' => $currency
                        ],
                        'discount' => [
                            'amount_off' => $this->formatPrice($discount),
                            'percent_off' => $regularPrice > 0
                                ? round(($discount / $regularPrice) * 100, 2)
                                : 0
                        ]
                    ],
                    'minimum_price' => [
                        'regular_price' => [
                            'value' => $this->formatPrice($regularPrice),
                            'currency' => $currency
                        ],
                        'final_price' => [
                            'value' => $this->formatPrice($finalPrice),
                            'currency' => $currency
                        ],
                        'discount' => [
                            'amount_off' => $this->formatPrice($discount),
                            'percent_off' => $regularPrice > 0
                                ? round(($discount / $regularPrice) * 100, 2)
                                : 0
                        ]
                    ]
                ];
            }

            $response->addResponse($request, $result);
        }

        return $response;
    }

    /**
     * Get final price for product
     *
     * @param  ProductInterface $product
     * @return float
     */
    private function getFinalPrice(ProductInterface $product): float
    {
        $finalPrice = $product->getFinalPrice();
        if ($finalPrice === null) {
            $finalPrice = $product->getPrice();

            // Apply special price if available
            if ($product->getSpecialPrice() !== null) {
                $specialPrice = (float)$product->getSpecialPrice();
                $specialFromDate = $product->getSpecialFromDate();
                $specialToDate = $product->getSpecialToDate();
                $now = time();

                if ($specialPrice > 0
                    && (!$specialFromDate || strtotime($specialFromDate) <= $now)
                    && (!$specialToDate || strtotime($specialToDate) >= $now)
                ) {
                    $finalPrice = min($finalPrice, $specialPrice);
                }
            }

            // Apply tier prices if available
            if ($product->getTierPrice()) {
                $qty = 1; // Default quantity
                $tierPrices = $product->getTierPrice();
                foreach ($tierPrices as $tierPrice) {
                    if ($qty >= $tierPrice['price_qty']) {
                        $finalPrice = min($finalPrice, $tierPrice['price']);
                    }
                }
            }
        }

        return (float)$finalPrice;
    }

    /**
     * Format price value
     *
     * @param  float $price
     * @return float
     */
    private function formatPrice(float $price): float
    {
        return (float)$this->priceCurrency->round($price);
    }
}
