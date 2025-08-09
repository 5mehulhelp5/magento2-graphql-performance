<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;

class ProductDataLoader extends BatchDataLoader
{
    public function __construct(
        ObjectManagerInterface $objectManager,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct($objectManager);
    }

    /**
     * Batch load products by IDs
     *
     * @param array $ids
     * @return array
     */
    protected function batchLoad(array $ids): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $ids, 'in')
            ->create();

        $products = $this->productRepository->getList($searchCriteria)->getItems();

        $result = [];
        foreach ($products as $product) {
            $result[$product->getId()] = $product;
        }

        return $result;
    }
}
