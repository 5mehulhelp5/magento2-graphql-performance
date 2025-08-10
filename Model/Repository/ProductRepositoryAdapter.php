<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Repository;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class ProductRepositoryAdapter extends AbstractRepositoryAdapter
{
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductRepositoryInterface $repository
    ) {
        parent::__construct($searchCriteriaBuilder, $repository);
    }

    public function getEntityType(): string
    {
        return 'product';
    }
}
