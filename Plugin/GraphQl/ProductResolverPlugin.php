<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\CatalogGraphQl\Model\Resolver\Products;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Resolver\Products\ProductsResolver;

class ProductResolverPlugin extends AbstractResolverPlugin
{
    public function __construct(ProductsResolver $optimizedResolver)
    {
        parent::__construct($optimizedResolver);
    }
}
