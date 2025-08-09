<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\Categories;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\BatchServiceContractResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\DataLoader\CategoryDataLoader;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;
use Magento\Store\Model\StoreManagerInterface;

class CategoriesResolver implements BatchServiceContractResolverInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ResolverCache $cache,
        private readonly CategoryDataLoader $dataLoader,
        private readonly QueryTimer $queryTimer,
        private readonly StoreManagerInterface $storeManager
    ) {}

    /**
     * Batch resolve categories
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

            // Get categories using optimized collection
            $collection = $this->getCategoryCollection($args);

            // Transform to GraphQL format
            $result = $this->transformCategoriesToGraphQL($collection, $info);

            // Cache the result
            $this->cache->set(
                $cacheKey,
                $result,
                ['catalog_category'],
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
     * Get optimized category collection
     *
     * @param array $args
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    private function getCategoryCollection(array $args): \Magento\Catalog\Model\ResourceModel\Category\Collection
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStore($this->storeManager->getStore())
            ->addAttributeToSelect('*')
            ->addFieldToFilter('is_active', 1);

        // Add filters
        if (isset($args['filters'])) {
            foreach ($args['filters'] as $field => $condition) {
                if ($field === 'url_key') {
                    $collection->addFieldToFilter('url_key', $condition);
                } elseif ($field === 'ids') {
                    $collection->addFieldToFilter('entity_id', ['in' => $condition]);
                } elseif ($field === 'parent_id') {
                    $collection->addFieldToFilter('parent_id', $condition);
                } elseif ($field === 'include_in_menu') {
                    $collection->addFieldToFilter('include_in_menu', $condition);
                }
            }
        }

        // Add pagination
        if (isset($args['pageSize'])) {
            $collection->setPageSize($args['pageSize']);
        }
        if (isset($args['currentPage'])) {
            $collection->setCurPage($args['currentPage']);
        }

        // Add sorting
        if (isset($args['sort'])) {
            foreach ($args['sort'] as $field => $direction) {
                $collection->addOrder($field, $direction);
            }
        } else {
            $collection->addOrder('position', 'ASC');
        }

        return $collection;
    }

    /**
     * Transform categories to GraphQL format
     *
     * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
     * @param ResolveInfo $info
     * @return array
     */
    private function transformCategoriesToGraphQL($collection, ResolveInfo $info): array
    {
        $items = [];
        foreach ($collection as $category) {
            $categoryData = [
                'id' => $category->getId(),
                'uid' => base64_encode('category/' . $category->getId()),
                'name' => $category->getName(),
                'url_key' => $category->getUrlKey(),
                'url_path' => $category->getUrlPath(),
                'path' => $category->getPath(),
                'level' => $category->getLevel(),
                'description' => $category->getDescription(),
                'product_count' => $category->getProductCount(),
                'is_active' => (bool)$category->getIsActive(),
                'include_in_menu' => (bool)$category->getIncludeInMenu(),
                '__typename' => 'CategoryTree'
            ];

            // Add requested fields based on GraphQL query
            $fields = $info->getFieldSelection();

            if (isset($fields['image'])) {
                $categoryData['image'] = $category->getImage() ? $this->getImageUrl($category->getImage()) : null;
            }

            if (isset($fields['breadcrumbs'])) {
                $categoryData['breadcrumbs'] = $this->getBreadcrumbs($category);
            }

            // Handle custom attributes for your specific case
            if (isset($fields['is_collection'])) {
                $categoryData['is_collection'] = (bool)$category->getData('is_collection');
            }
            if (isset($fields['is_brand'])) {
                $categoryData['is_brand'] = (bool)$category->getData('is_brand');
            }
            if (isset($fields['is_featured'])) {
                $categoryData['is_featured'] = (bool)$category->getData('is_featured');
            }

            $items[] = $categoryData;
        }

        return [
            'items' => $items,
            'total_count' => $collection->getSize(),
            'page_info' => [
                'total_pages' => ceil($collection->getSize() / ($collection->getPageSize() ?: 20)),
                'current_page' => $collection->getCurPage(),
                'page_size' => $collection->getPageSize()
            ]
        ];
    }

    /**
     * Get image URL
     *
     * @param string $image
     * @return string|null
     */
    private function getImageUrl(string $image): ?string
    {
        if (!$image) {
            return null;
        }

        $store = $this->storeManager->getStore();
        return $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/category/' . $image;
    }

    /**
     * Get category breadcrumbs
     *
     * @param \Magento\Catalog\Model\Category $category
     * @return array
     */
    private function getBreadcrumbs($category): array
    {
        $breadcrumbs = [];
        $path = $category->getPath();
        $pathIds = explode('/', $path);
        array_shift($pathIds); // Remove root category
        array_pop($pathIds); // Remove current category

        if (!empty($pathIds)) {
            $parentCategories = $this->dataLoader->loadMany($pathIds);
            foreach ($pathIds as $categoryId) {
                if (isset($parentCategories[$categoryId])) {
                    $parent = $parentCategories[$categoryId];
                    $breadcrumbs[] = [
                        'category_id' => $parent->getId(),
                        'category_name' => $parent->getName(),
                        'category_level' => $parent->getLevel(),
                        'category_url_key' => $parent->getUrlKey(),
                        'category_url_path' => $parent->getUrlPath()
                    ];
                }
            }
        }

        return $breadcrumbs;
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
            'categories_query',
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
}
