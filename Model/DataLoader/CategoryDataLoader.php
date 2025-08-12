<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use Magento\Framework\ObjectManagerInterface;
use Sterk\GraphQlPerformance\Model\Repository\CategoryRepositoryAdapter;

/**
 * Data loader for category entities
 *
 * This class provides efficient loading of category data through batch loading.
 * It uses a repository adapter to optimize database queries when loading
 * multiple categories simultaneously.
 */
class CategoryDataLoader extends BatchDataLoader
{
    /**
     * @param ObjectManagerInterface $objectManager Object manager
     * @param CategoryRepositoryAdapter $repository Category repository adapter
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        private readonly CategoryRepositoryAdapter $repository
    ) {
        parent::__construct($objectManager);
    }

    /**
     * Batch load categories by IDs
     *
     * @param  array $ids
     * @return array
     */
    protected function batchLoad(array $ids): array
    {
        return $this->repository->getByIds($ids);
    }
}
