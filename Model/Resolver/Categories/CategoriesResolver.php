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

/**
 * GraphQL resolver for categories
 *
 * This resolver handles batch loading of categories with support for filtering,
 * sorting, and pagination. It transforms category data into the GraphQL format
 * and handles related entities like breadcrumbs and custom attributes.
 */
class CategoriesResolver extends AbstractResolver
{
    use FieldSelectionTrait;
    use PaginationTrait;

    /**
     * @param CategoryRepositoryInterface $categoryRepository Category repository
     * @param CategoryCollectionFactory $categoryCollectionFactory Category collection factory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder Search criteria builder
     * @param CategoryDataLoader $dataLoader Category data loader
     * @param StoreManagerInterface $storeManager Store manager
     * @param ResolverCache $cache Cache service
     * @param QueryTimer $queryTimer Query timing service
     */
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        protected readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly CategoryDataLoader $dataLoader,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly ResolverCache $cache,
        private readonly QueryTimer $queryTimer
    ) {
        parent::__construct($categoryCollectionFactory, $storeManager, $cache);
    }

    /**
     * Get entity type code
     *
     * @return string Entity type code
     */
    protected function getEntityType(): string
    {
        return 'category';
    }

    /**
     * Get cache tags for invalidation
     *
     * @return array Cache tag identifiers
     */
    protected function getCacheTags(): array
    {
        return ['catalog_category'];
    }

    /**
     * Get category IDs from request value
     *
     * @param array $value
     * @return array
     */
    protected function getCategoryIds(array $value): array
    {
        if (isset($value['id'])) {
            return [$value['id']];
        }

        if (isset($value['ids']) && is_array($value['ids'])) {
            return $value['ids'];
        }

        return [];
    }

    /**
     * Process categories and return result
     *
     * @param array $categoryIds
     * @param array $fields
     * @return array
     */
    protected function processCategories(array $categoryIds, array $fields): array
    {
        $items = [];
        foreach ($categoryIds as $categoryId) {
            if (!isset($this->categoryCache[$categoryId])) {
                continue;
            }

            $category = $this->categoryCache[$categoryId];
            $categoryData = $this->getBaseCategoryData($category);

            if (isset($fields['image'])) {
                $categoryData['image'] = $category->getImage() ? $this->getImageUrl($category->getImage()) : null;
            }

            if (isset($fields['breadcrumbs'])) {
                $categoryData['breadcrumbs'] = $this->getBreadcrumbs($category);
            }

            // Handle custom attributes
            $customAttributes = ['is_collection', 'is_brand', 'is_featured'];
            foreach ($customAttributes as $attribute) {
                if (isset($fields[$attribute])) {
                    $categoryData[$attribute] = (bool)$category->getData($attribute);
                }
            }

            $items[] = $categoryData;
        }

        return $items;
    }

    /**
     * Resolve category data for GraphQL query
     *
     * This method handles the main resolution logic for category queries. It
     * retrieves categories using an optimized collection, applies filters and
     * sorting, and transforms the data into the GraphQL format.
     *
     * @param  Field       $field   Field to resolve
     * @param  mixed       $context Context object
     * @param  ResolveInfo $info    Resolve info
     * @param  array       $value   Parent value
     * @param  array       $args    Query arguments
     * @return array       Resolved category data
     */
    protected function resolveData(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = [],
        array $args = []
    ): array {
        // Get categories using optimized collection
        $collection = $this->getCategoryCollection($args);

        // Transform to GraphQL format
        return $this->transformCategoriesToGraphQL($collection, $info, $args);
    }

    /**
     * Get optimized category collection
     *
     * @param  array $args
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
     * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection Category collection
     * @param ResolveInfo                                             $info       Resolve info
     * @param array                                                   $args       Query arguments
     * @return array                                                             Transformed category data
     */
    private function transformCategoriesToGraphQL(\Magento\Catalog\Model\ResourceModel\Category\Collection $collection, ResolveInfo $info, array $args): array {
        $items = [];
        foreach ($collection as $category) {
            $categoryData = $this->getBaseCategoryData($category);

            if ($this->isFieldRequested($info, 'image')) {
                $categoryData['image'] = $category->getImage() ? $this->getImageUrl($category->getImage()) : null;
            }

            if ($this->isFieldRequested($info, 'breadcrumbs')) {
                $categoryData['breadcrumbs'] = $this->getBreadcrumbs($category);
            }

            // Handle custom attributes
            $customAttributes = ['is_collection', 'is_brand', 'is_featured'];
            foreach ($customAttributes as $attribute) {
                $categoryData = $this->addFieldIfRequested(
                    $categoryData,
                    $info,
                    $attribute,
                    (bool)$category->getData($attribute)
                );
            }

            $items[] = $categoryData;
        }

        return $this->getPaginatedResult(
            $items,
            $collection->getSize(),
            [
                'pageSize' => $collection->getPageSize() ?: 20,
                'currentPage' => $collection->getCurPage()
            ]
        );
    }

    /**
     * Get base category data for GraphQL response
     *
     * @param \Magento\Catalog\Model\Category $category Category entity
     * @return array Base category data
     */
    private function getBaseCategoryData($category): array
    {
        return [
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
    }

    /**
     * Get image URL
     *
     * @param  string $image
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
     * @param  \Magento\Catalog\Model\Category $category
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
     * Generate cache key for category
     *
     * @param int $categoryId
     * @param int $storeId
     * @return string
     */
    protected function generateCacheKey(int $categoryId, int $storeId): string
    {
        return parent::generateCacheKey($categoryId, $storeId);
    }

    /**
     * Generate query cache key
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array       $value
     * @param  array       $args
     * @return string
     */
    private function generateQueryCacheKey(
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
