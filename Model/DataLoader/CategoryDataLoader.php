<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use Magento\Framework\ObjectManagerInterface;
use Sterk\GraphQlPerformance\Model\Repository\CategoryRepositoryAdapter;

class CategoryDataLoader extends BatchDataLoader
{
    public function __construct(
        ObjectManagerInterface $objectManager,
        private readonly CategoryRepositoryAdapter $repository
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
        return $this->repository->getByIds($ids);
    }
}
