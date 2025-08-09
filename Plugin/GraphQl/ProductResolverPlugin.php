<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\CatalogGraphQl\Model\Resolver\Products;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Resolver\Products\ProductsResolver;

class ProductResolverPlugin
{
    public function __construct(
        private readonly ProductsResolver $optimizedResolver
    ) {}

    /**
     * Replace the original products resolver with our optimized version
     *
     * @param Products $subject
     * @param callable $proceed
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function aroundResolve(
        Products $subject,
        callable $proceed,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        return $this->optimizedResolver->resolve($field, $context, $info, $value ?? [], $args ?? []);
    }
}
