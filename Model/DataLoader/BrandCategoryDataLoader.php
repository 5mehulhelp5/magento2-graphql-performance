<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Data loader for brand categories
 *
 * This class provides efficient loading of brand category data, with support
 * for batch loading and filtering. It specifically handles categories marked
 * as brands at level 3 in the category hierarchy.
 */
class BrandCategoryDataLoader extends BatchDataLoader
{
    /**
     * @param ObjectManagerInterface $objectManager Object manager
     * @param CategoryCollectionFactory $categoryCollectionFactory Category collection factory
     * @param StoreManagerInterface $storeManager Store manager
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($objectManager);
    }

    /**
     * Batch load brand categories
     *
     * @param  array $ids
     * @return array
     */
    protected function batchLoad(array $ids): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStore($this->storeManager->getStore())
            ->addAttributeToSelect(['name', 'url_path', 'thumbnail'])
            ->addAttributeToFilter('entity_id', ['in' => $ids])
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->addAttributeToFilter('is_brand', 1)
            ->addAttributeToFilter('level', 3)
            ->setOrder('name', 'ASC');

        $result = [];
        foreach ($collection as $category) {
            $result[$category->getId()] = $category;
        }

        return $result;
    }

    /**
     * Load all brand categories
     *
     * @return array
     */
    public function loadAllBrands(): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStore($this->storeManager->getStore())
            ->addAttributeToSelect(['name', 'url_path', 'thumbnail'])
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->addAttributeToFilter('is_brand', 1)
            ->addAttributeToFilter('level', 3)
            ->setOrder('name', 'ASC');

        $result = [];
        foreach ($collection as $category) {
            $result[$category->getId()] = $category;
        }

        return $result;
    }
}
