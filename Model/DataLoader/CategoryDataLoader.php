<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;

class CategoryDataLoader extends BatchDataLoader
{
    public function __construct(
        ObjectManagerInterface $objectManager,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct($objectManager);
    }

    /**
     * Batch load categories by IDs
     *
     * @param array $ids
     * @return array
     */
    protected function batchLoad(array $ids): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $ids, 'in')
            ->create();

        $categories = $this->categoryRepository->getList($searchCriteria)->getItems();

        $result = [];
        foreach ($categories as $category) {
            $result[$category->getId()] = $category;
        }

        return $result;
    }
}
