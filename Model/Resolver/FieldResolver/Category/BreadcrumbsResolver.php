<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Category;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;

class BreadcrumbsResolver implements BatchResolverInterface
{
    private array $pathCache = [];
    private array $categoryCache = [];

    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {}

    /**
     * Batch resolve category breadcrumbs
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
    ): array {
        /** @var \Magento\Catalog\Api\Data\CategoryInterface[] $categories */
        $categories = $value['categories'] ?? [];
        $result = [];

        if (empty($categories)) {
            return $result;
        }

        // Get all category paths
        $pathIds = [];
        foreach ($categories as $category) {
            $path = $category->getPath();
            $ids = explode('/', $path);
            array_shift($ids); // Remove root category
            array_pop($ids); // Remove current category
            foreach ($ids as $id) {
                $pathIds[] = $id;
            }

        // Load all parent categories in batch
        if (!empty($pathIds)) {
            $this->loadCategories(array_unique($pathIds));
        }

        // Build breadcrumbs for each category
        foreach ($categories as $category) {
            $categoryId = $category->getId();
            $result[$categoryId] = $this->buildBreadcrumbs($category);
        }

        return $result;
    }

    /**
     * Load categories by IDs
     *
     * @param array $categoryIds
     * @return void
     */
    private function loadCategories(array $categoryIds): void
    {
        $uncachedIds = array_diff($categoryIds, array_keys($this->categoryCache));

        if (!empty($uncachedIds)) {
            $collection = $this->categoryCollectionFactory->create();
            $collection->setStore($this->storeManager->getStore())
                ->addAttributeToSelect(['name', 'url_key', 'url_path', 'path', 'level'])
                ->addFieldToFilter('entity_id', ['in' => $uncachedIds]);

            foreach ($collection as $category) {
                $this->categoryCache[$category->getId()] = $category;
            }
        }
    }

    /**
     * Build breadcrumbs for a category
     *
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @return array
     */
    private function buildBreadcrumbs($category): array
    {
        $path = $category->getPath();

        if (isset($this->pathCache[$path])) {
            return $this->pathCache[$path];
        }

        $breadcrumbs = [];
        $pathIds = explode('/', $path);
        array_shift($pathIds); // Remove root category
        array_pop($pathIds); // Remove current category

        foreach ($pathIds as $pathId) {
            $parent = $this->categoryCache[$pathId] ?? null;
            if ($parent) {
                $breadcrumbs[] = [
                    'category_id' => $parent->getId(),
                    'category_name' => $parent->getName(),
                    'category_level' => $parent->getLevel(),
                    'category_url_key' => $parent->getUrlKey(),
                    'category_url_path' => $parent->getUrlPath()
                ];
            }
        }

        $this->pathCache[$path] = $breadcrumbs;
        return $breadcrumbs;
    }
}
