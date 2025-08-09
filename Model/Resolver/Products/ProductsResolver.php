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

class ProductsResolver implements BatchServiceContractResolverInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ResolverCache $cache,
        private readonly ProductDataLoader $dataLoader,
        private readonly QueryTimer $queryTimer
    ) {}

    /**
     * Batch resolve products
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
    ) {
        $this->queryTimer->start($info->operation->name->value, $info->operation->loc->source->body);

        try {
            // Try to get from cache first
            $cacheKey = $this->generateCacheKey($field, $context, $info, $value, $args);
            $cachedData = $this->cache->get($cacheKey);

            if ($cachedData !== null) {
                $this->queryTimer->stop($info->operation->name->value, $info->operation->loc->source->body, true);
                return $cachedData;
            }

            // Build search criteria from arguments
            $searchCriteria = $this->buildSearchCriteria($args);

            // Get products using data loader for batching
            $products = $this->dataLoader->loadMany($searchCriteria);

            // Transform to GraphQL format
            $result = $this->transformProductsToGraphQL($products, $info);

            // Cache the result
            $this->cache->set(
                $cacheKey,
                $result,
                ['catalog_product'],
                3600 // 1 hour cache
            );

            $this->queryTimer->stop($info->operation->name->value, $info->operation->loc->source->body);

            return $result;
        } catch (\Exception $e) {
            $this->queryTimer->stop($info->operation->name->value, $info->operation->loc->source->body);
            throw $e;
        }
    }

    /**
     * Generate cache key
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array $value
     * @param array $args
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
     * @param array $args
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
     * @param array $products
     * @param ResolveInfo $info
     * @return array
     */
    private function transformProductsToGraphQL(array $products, ResolveInfo $info): array
    {
        $items = [];
        foreach ($products as $product) {
            $productData = [
                'id' => $product->getId(),
                'uid' => base64_encode('product/' . $product->getId()),
                'name' => $product->getName(),
                'sku' => $product->getSku(),
                'url_key' => $product->getUrlKey(),
                'stock_status' => $product->getExtensionAttributes()->getStockItem()->getIsInStock() ? 'IN_STOCK' : 'OUT_OF_STOCK',
                '__typename' => 'SimpleProduct', // Add logic for different product types
            ];

            // Add requested fields based on GraphQL query
            $fields = $info->getFieldSelection();
            if (isset($fields['price_range'])) {
                $productData['price_range'] = $this->getPriceRange($product);
            }

            if (isset($fields['small_image'])) {
                $productData['small_image'] = [
                    'url' => $product->getSmallImage()
                ];
            }

            // Add custom attributes if requested
            $customAttributes = ['es_saat_mekanizma', 'es_kasa_capi', 'es_kasa_cinsi', 'es_kordon_tipi', 'manufacturer'];
            foreach ($customAttributes as $attribute) {
                if (isset($fields[$attribute])) {
                    $productData[$attribute] = $this->getAttributeValueLabel($product, $attribute);
                }
            }

            $items[] = $productData;
        }

        return [
            'items' => $items,
            'total_count' => count($products),
            'page_info' => [
                'total_pages' => ceil(count($products) / ($args['pageSize'] ?? 20))
            ]
        ];
    }

    /**
     * Get price range data
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
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
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param string $attributeCode
     * @return array
     */
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
