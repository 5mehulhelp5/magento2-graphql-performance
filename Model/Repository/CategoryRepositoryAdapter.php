<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Repository;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CategoryRepositoryAdapter extends AbstractRepositoryAdapter
{
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CategoryRepositoryInterface $repository
    ) {
        parent::__construct($searchCriteriaBuilder, $repository);
    }

    public function getEntityType(): string
    {
        return 'category';
    }
}
