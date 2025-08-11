<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use MagentoTeknik\EdipCustom\Model\Resolver\BrandCategoriesQuery;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Resolver\BrandCategories\BrandCategoriesResolver;

/**
 * Plugin for optimizing brand categories resolver
 */
class BrandCategoriesResolverPlugin extends AbstractResolverPlugin
{
}
