<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Category;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Store\Model\StoreManagerInterface;

/**
 * GraphQL resolver for category breadcrumbs
 *
 * This resolver efficiently generates breadcrumb paths for multiple categories
 * in a single batch operation. It uses caching to improve performance and
 * handles loading of parent categories in batches.
 */
class BreadcrumbsResolver implements BatchResolverInterface
{
    /**
     * @var array<string, array<array{
     *     category_id: int,
     *     category_name: string,
     *     category_level: int,
     *     category_url_key: string,
     *     category_url_path: string
     * }>> Cache for category breadcrumb paths
     */
    private array $pathCache = [];

    /**
     * @var array<int, \Magento\Catalog\Api\Data\CategoryInterface> Cache for loaded category objects
     */
    private array $categoryCache = [];

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory Category collection factory
     * @param StoreManagerInterface $storeManager Store manager
     */
    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Batch resolve category breadcrumbs
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
            $categories = $value['categories'] ?? [];

            if (empty($categories)) {
                $response->addResponse($request, []);
                continue;
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
            }

            // Load all parent categories in batch
            if (!empty($pathIds)) {
                $this->loadCategories(array_unique($pathIds));
            }

            // Build breadcrumbs for each category
            $result = [];
            foreach ($categories as $category) {
                $categoryId = $category->getId();
                $result[$categoryId] = $this->buildBreadcrumbs($category);
            }

            $response->addResponse($request, $result);
        }

        return $response;
    }

    /**
     * Load categories by IDs
     *
     * @param  array $categoryIds
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
     * @param  \Magento\Catalog\Api\Data\CategoryInterface $category
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
