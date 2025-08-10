<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use MagentoTeknik\EdipCustom\Model\Resolver\BrandCategoriesQuery;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Resolver\BrandCategories\BrandCategoriesResolver;

class BrandCategoriesResolverPlugin extends AbstractResolverPlugin
{
    public function __construct(BrandCategoriesResolver $optimizedResolver)
    {
        parent::__construct($optimizedResolver);
    }
}
