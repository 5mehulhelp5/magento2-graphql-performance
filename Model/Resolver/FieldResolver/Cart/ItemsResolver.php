<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Cart;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ItemsResolver implements BatchResolverInterface
{
    /**
     * @var array Cart item objects cache
     */
    private array $itemCache = [];

    /**
     * @var array Product objects cache
     */
    private array $productCache = [];

    public function __construct(
        private readonly CartItemRepositoryInterface $cartItemRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly StoreManagerInterface $storeManager,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Batch resolve cart items
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

        // Load items for all carts in batch
        $this->loadCartItems($cartIds);

        // Get selected fields
        $fields = $info->getFieldSelection();

        foreach ($carts as $cart) {
            $cartId = $cart->getId();
            $result[$cartId] = $this->transformCartItems(
                $this->itemCache[$cartId] ?? [],
                $fields
            );
        }

        return $result;
    }

    /**
     * Load cart items in batch
     *
     * @param  array $cartIds
     * @return void
     */
    private function loadCartItems(array $cartIds): void
    {
        $uncachedIds = array_diff($cartIds, array_keys($this->itemCache));

        if (empty($uncachedIds)) {
            return;
        }

        // Load items for each cart
        foreach ($uncachedIds as $cartId) {
            try {
                $items = $this->cartItemRepository->getList($cartId);
                $this->itemCache[$cartId] = $items;

                // Collect product IDs
                foreach ($items as $item) {
                    $productId = $item->getProduct()->getId();
                    if (!isset($this->productCache[$productId])) {
                        $this->productCache[$productId] = $item->getProduct();
                    }
                }
            } catch (\Exception $e) {
                $this->itemCache[$cartId] = [];
            }
        }

        // Load all products in batch if needed
        $this->loadProducts();
    }

    /**
     * Load products in batch
     *
     * @return void
     */
    private function loadProducts(): void
    {
        $productIds = array_keys($this->productCache);
        if (empty($productIds)) {
            return;
        }

        try {
            $products = $this->productRepository->getList(
                $this->buildSearchCriteria(['entity_id' => ['in' => $productIds]])
            )->getItems();

            foreach ($products as $product) {
                $this->productCache[$product->getId()] = $product;
            }
        } catch (\Exception $e) {
            // Silently handle product loading errors to avoid breaking the GraphQL response
            // Individual product errors will be reflected in the result array
            $this->logger?->error(
                'Error loading products: ' . $e->getMessage(),
                [
                'product_ids' => $productIds,
                'exception' => $e
                ]
            );
        }
    }

    /**
     * Transform cart items to GraphQL format
     *
     * @param  array $items
     * @param  array $fields
     * @return array
     */
    private function transformCartItems(array $items, array $fields): array
    {
        $result = [];
        $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();

        foreach ($items as $item) {
            $itemData = [
                'id' => $item->getItemId(),
                'uid' => base64_encode('cart_item/' . $item->getItemId()),
                'quantity' => $item->getQty(),
                'product' => $this->getProductData($item->getProduct(), $fields['product'] ?? [])
            ];

            if (isset($fields['prices'])) {
                $itemData['prices'] = $this->getPriceData($item, $currency);
            }

            if (isset($fields['customizable_options'])) {
                $itemData['customizable_options'] = $this->getCustomOptions($item);
            }

            $result[] = $itemData;
        }

        return $result;
    }

    /**
     * Get product data
     *
     * @param  \Magento\Catalog\Api\Data\ProductInterface $product
     * @param  array                                      $fields
     * @return array
     */
    private function getProductData($product, array $fields): array
    {
        $data = [
            'id' => $product->getId(),
            'uid' => base64_encode('product/' . $product->getId()),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'type_id' => $product->getTypeId(),
            '__typename' => 'SimpleProduct' // Add logic for different product types
        ];

        if (isset($fields['url_key'])) {
            $data['url_key'] = $product->getUrlKey();
        }

        if (isset($fields['small_image'])) {
            $data['small_image'] = [
                'url' => $product->getSmallImage()
                    ? $this->getMediaUrl($product->getSmallImage())
                    : null
            ];
        }

        return $data;
    }

    /**
     * Get price data
     *
     * @param  \Magento\Quote\Api\Data\CartItemInterface $item
     * @param  string                                    $currency
     * @return array
     */
    private function getPriceData($item, string $currency): array
    {
        $price = $item->getPrice();
        $rowTotal = $item->getRowTotal();
        $discountAmount = $item->getDiscountAmount();

        return [
            'price' => [
                'value' => $this->formatPrice($price),
                'currency' => $currency
            ],
            'row_total' => [
                'value' => $this->formatPrice($rowTotal),
                'currency' => $currency
            ],
            'row_total_including_tax' => [
                'value' => $this->formatPrice($rowTotal + $item->getTaxAmount()),
                'currency' => $currency
            ],
            'discounts' => $discountAmount > 0 ? [
                [
                    'amount' => [
                        'value' => $this->formatPrice($discountAmount),
                        'currency' => $currency
                    ]
                ]
            ] : []
        ];
    }

    /**
     * Get custom options
     *
     * @param  \Magento\Quote\Api\Data\CartItemInterface $item
     * @return array
     */
    private function getCustomOptions($item): array
    {
        $options = [];
        $buyRequest = $item->getBuyRequest();

        if ($buyRequest && $buyRequest->getOptions()) {
            foreach ($buyRequest->getOptions() as $optionId => $optionValue) {
                $option = $item->getProduct()->getOptionById($optionId);
                if ($option) {
                    $options[] = [
                        'id' => $optionId,
                        'label' => $option->getTitle(),
                        'value' => $optionValue,
                        'type' => $option->getType()
                    ];
                }
            }
        }

        return $options;
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

    /**
     * Get media URL
     *
     * @param  string $path
     * @return string
     */
    private function getMediaUrl(string $path): string
    {
        return $this->storeManager->getStore()
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $path;
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
