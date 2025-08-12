<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Category;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;

/**
 * GraphQL resolver for category children
 *
 * This resolver handles batch loading of category children with support for
 * caching and efficient data retrieval. It transforms category data into the
 * GraphQL format and handles related entities like category IDs and URLs.
 */
class ChildrenResolver implements BatchResolverInterface
{
    /**
     * @var array<int, array<array{
     *     id: int,
     *     uid: string,
     *     name: string,
     *     url_key: string,
     *     url_path: string,
     *     is_active: bool,
     *     include_in_menu: bool,
     *     position: int,
     *     __typename: string
     * }>> Cache of children data by parent category ID
     */
    private array $childrenCache = [];

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
     * Batch resolve category children
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
 * @var \Magento\Catalog\Api\Data\CategoryInterface[] $categories
*/
        $categories = $value['categories'] ?? [];
        $result = [];

        // Get all parent IDs
        $parentIds = array_map(
            function ($category) {
                return $category->getId();
            },
            $categories
        );

        // Load children data in batch
        $childrenData = $this->getChildrenData($parentIds);

        foreach ($categories as $category) {
            $categoryId = $category->getId();
            $result[$categoryId] = $childrenData[$categoryId] ?? [];
        }

        return $result;
    }

    /**
     * Get children data for multiple categories
     *
     * @param  array $parentIds
     * @return array
     */
    private function getChildrenData(array $parentIds): array
    {
        $result = [];
        $uncachedIds = array_diff($parentIds, array_keys($this->childrenCache));

        if (!empty($uncachedIds)) {
            $collection = $this->categoryCollectionFactory->create();
            $collection->setStore($this->storeManager->getStore())
                ->addAttributeToSelect(['name', 'url_key', 'url_path', 'is_active', 'include_in_menu'])
                ->addFieldToFilter('parent_id', ['in' => $uncachedIds])
                ->addFieldToFilter('is_active', 1)
                ->addFieldToFilter('include_in_menu', 1)
                ->setOrder('position', 'ASC');

            // Group children by parent ID
            foreach ($collection as $child) {
                $parentId = $child->getParentId();
                if (!isset($this->childrenCache[$parentId])) {
                    $this->childrenCache[$parentId] = [];
                }

                $this->childrenCache[$parentId][] = [
                    'id' => $child->getId(),
                    'uid' => base64_encode('category/' . $child->getId()),
                    'name' => $child->getName(),
                    'url_key' => $child->getUrlKey(),
                    'url_path' => $child->getUrlPath(),
                    'is_active' => (bool)$child->getIsActive(),
                    'include_in_menu' => (bool)$child->getIncludeInMenu(),
                    'position' => $child->getPosition(),
                    '__typename' => 'CategoryTree'
                ];
            }

            // Set empty array for categories without children
            foreach ($uncachedIds as $parentId) {
                if (!isset($this->childrenCache[$parentId])) {
                    $this->childrenCache[$parentId] = [];
                }
            }
        }

        foreach ($parentIds as $parentId) {
            $result[$parentId] = $this->childrenCache[$parentId] ?? [];
        }

        return $result;
    }
}
