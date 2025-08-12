<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Repository;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Repository adapter for product entities
 *
 * This adapter provides optimized access to product data through the Magento
 * product repository, adding batch loading capabilities and standardized
 * repository operations.
 */
class ProductRepositoryAdapter extends AbstractRepositoryAdapter
{

    /**
     * Get entity type code
     *
     * @return string
     */
    public function getEntityType(): string
    {
        return 'product';
    }
}
