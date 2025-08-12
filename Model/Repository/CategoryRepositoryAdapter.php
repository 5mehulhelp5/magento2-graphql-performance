<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Repository;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Repository adapter for category entities
 *
 * This adapter provides optimized access to category data through the Magento
 * category repository, adding batch loading capabilities and standardized
 * repository operations.
 */
class CategoryRepositoryAdapter extends AbstractRepositoryAdapter
{
    /**
     * Get entity type code
     *
     * @return string
     */
    public function getEntityType(): string
    {
        return 'category';
    }
}
