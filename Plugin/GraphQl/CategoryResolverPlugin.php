<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\CatalogGraphQl\Model\Resolver\Categories;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Resolver\Categories\CategoriesResolver;

class CategoryResolverPlugin
{
    public function __construct(
        private readonly CategoriesResolver $optimizedResolver
    ) {}

    /**
     * Replace the original categories resolver with our optimized version
     *
     * @param Categories $subject
     * @param callable $proceed
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function aroundResolve(
        Categories $subject,
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
