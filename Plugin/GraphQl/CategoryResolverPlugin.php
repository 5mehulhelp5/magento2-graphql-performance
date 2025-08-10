<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\CatalogGraphQl\Model\Resolver\Categories;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Resolver\Categories\CategoriesResolver;

class CategoryResolverPlugin extends AbstractResolverPlugin
{
    public function __construct(CategoriesResolver $optimizedResolver)
    {
        parent::__construct($optimizedResolver);
    }
}
