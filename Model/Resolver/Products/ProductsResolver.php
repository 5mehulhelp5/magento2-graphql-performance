<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\Products;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\BatchServiceContractResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\DataLoader\ProductDataLoader;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;

class ProductsResolver extends AbstractResolver
{
    use FieldSelectionTrait;
    use PaginationTrait;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ProductDataLoader $dataLoader,
        ResolverCache $cache,
        QueryTimer $queryTimer
    ) {
        parent::__construct($cache, $queryTimer);
    }

    /**
     * Batch resolve products
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array       $value
     * @param  array       $args
     * @return array
     */
    protected function getEntityType(): string
    {
        return 'product';
    }

    protected function getCacheTags(): array
    {
        return ['catalog_product'];
    }

    protected function resolveData(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = [],
        array $args = []
    ): array {
        // Build search criteria from arguments
        $searchCriteria = $this->buildSearchCriteria($args);

        // Get products using data loader for batching
        $products = $this->dataLoader->loadMany($searchCriteria);

        // Transform to GraphQL format
        return $this->transformProductsToGraphQL($products, $info, $args);
    }

    /**
     * Generate cache key
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array       $value
     * @param  array       $args
     * @return string
     */
    private function generateCacheKey(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value,
        array $args
    ): string {
        $keyParts = [
            'products_query',
            $field->getName(),
            $info->operation->name->value,
            json_encode($args),
            json_encode($value)
        ];

        if (method_exists($context, 'getExtensionAttributes')) {
            $store = $context->getExtensionAttributes()->getStore();
            if ($store) {
                $keyParts[] = $store->getId();
            }
        }

        return implode(':', array_filter($keyParts));
    }

    /**
     * Build search criteria from GraphQL arguments
     *
     * @param  array $args
     * @return \Magento\Framework\Api\SearchCriteria
     */
    private function buildSearchCriteria(array $args): \Magento\Framework\Api\SearchCriteria
    {
        $this->searchCriteriaBuilder->setPageSize($args['pageSize'] ?? 20);
        $this->searchCriteriaBuilder->setCurrentPage($args['currentPage'] ?? 1);

        if (isset($args['filters'])) {
            foreach ($args['filters'] as $field => $condition) {
                if (is_array($condition)) {
                    foreach ($condition as $type => $value) {
                        $this->searchCriteriaBuilder->addFilter($field, $value, $type);
                    }
                } else {
                    $this->searchCriteriaBuilder->addFilter($field, $condition, 'eq');
                }
            }
        }

        if (isset($args['sort'])) {
            foreach ($args['sort'] as $field => $direction) {
                $this->searchCriteriaBuilder->addSortOrder($field, $direction);
            }
        }

        return $this->searchCriteriaBuilder->create();
    }

    /**
     * Transform products to GraphQL format
     *
     * @param  array       $products
     * @param  ResolveInfo $info
     * @return array
     */
    private function transformProductsToGraphQL(array $products, ResolveInfo $info, array $args): array
    {
        $items = [];
        foreach ($products as $product) {
            $productData = $this->getBaseProductData($product);

            if ($this->isFieldRequested($info, 'price_range')) {
                $productData['price_range'] = $this->getPriceRange($product);
            }

            if ($this->isFieldRequested($info, 'small_image')) {
                $productData['small_image'] = [
                    'url' => $product->getSmallImage()
                ];
            }

            // Add custom attributes if requested
            $customAttributes = [
                'es_saat_mekanizma',
                'es_kasa_capi',
                'es_kasa_cinsi',
                'es_kordon_tipi',
                'manufacturer'
            ];
            foreach ($customAttributes as $attribute) {
                $productData = $this->addFieldIfRequested(
                    $productData,
                    $info,
                    $attribute,
                    $this->getAttributeValueLabel($product, $attribute)
                );
            }

            $items[] = $productData;
        }

        return $this->getPaginatedResult($items, count($products), $args);
    }

    private function getBaseProductData($product): array
    {
        return [
            'id' => $product->getId(),
            'uid' => base64_encode('product/' . $product->getId()),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'url_key' => $product->getUrlKey(),
            'stock_status' => $this->getStockStatus($product),
            '__typename' => 'SimpleProduct', // Add logic for different product types
        ];
    }

    /**
     * Get price range data
     *
     * @param  \Magento\Catalog\Api\Data\ProductInterface $product
     * @return array
     */
    private function getPriceRange($product): array
    {
        $regularPrice = $product->getPrice();
        $finalPrice = $product->getFinalPrice();

        return [
            'maximum_price' => [
                'regular_price' => [
                    'value' => $regularPrice,
                    'currency' => 'USD' // Add proper currency handling
                ],
                'final_price' => [
                    'value' => $finalPrice,
                    'currency' => 'USD'
                ],
                'discount' => [
                    'amount_off' => $regularPrice - $finalPrice
                ]
            ]
        ];
    }

    /**
     * Get attribute value and label
     *
     * @param  \Magento\Catalog\Api\Data\ProductInterface $product
     * @param  string                                     $attributeCode
     * @return array
     */
    /**
     * Get stock status for a product
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return string
     */
    private function getStockStatus($product): string
    {
        return $product->getExtensionAttributes()
            ->getStockItem()
            ->getIsInStock() ? 'IN_STOCK' : 'OUT_OF_STOCK';
    }

    private function getAttributeValueLabel($product, string $attributeCode): array
    {
        $attribute = $product->getResource()->getAttribute($attributeCode);
        if (!$attribute) {
            return ['value' => '', 'label' => ''];
        }

        $value = $product->getData($attributeCode);
        $label = $attribute->getSource()->getOptionText($value);

        return [
            'value' => $value,
            'label' => $label ?: $value
        ];
    }
}
