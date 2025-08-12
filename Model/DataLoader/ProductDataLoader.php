<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use Magento\Framework\ObjectManagerInterface;
use Sterk\GraphQlPerformance\Model\Repository\ProductRepositoryAdapter;

/**
 * Data loader for product data
 *
 * This class provides efficient loading of product data through batch loading.
 * It uses a repository adapter to optimize database queries when loading
 * multiple products simultaneously.
 */
class ProductDataLoader extends BatchDataLoader
{
    /**
     * @param ObjectManagerInterface $objectManager Object manager
     * @param ProductRepositoryAdapter $repository Product repository adapter
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        private readonly ProductRepositoryAdapter $repository
    ) {
        parent::__construct($objectManager);
    }

    /**
     * Batch load products by IDs
     *
     * @param  array $ids
     * @return array
     */
    protected function batchLoad(array $ids): array
    {
        return $this->repository->getByIds($ids);
    }
}
