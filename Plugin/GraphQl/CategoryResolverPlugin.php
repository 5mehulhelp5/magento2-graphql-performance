<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\CatalogGraphQl\Model\Resolver\Categories;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Resolver\Categories\CategoriesResolver;

/**
 * Plugin for optimizing category resolver
 */
class CategoryResolverPlugin extends AbstractResolverPlugin
{
}
