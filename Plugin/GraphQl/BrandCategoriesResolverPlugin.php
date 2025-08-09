<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use MagentoTeknik\EdipCustom\Model\Resolver\BrandCategoriesQuery;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Resolver\BrandCategories\BrandCategoriesResolver;

class BrandCategoriesResolverPlugin
{
    public function __construct(
        private readonly BrandCategoriesResolver $optimizedResolver
    ) {}

    /**
     * Replace the original brand categories resolver with our optimized version
     *
     * @param BrandCategoriesQuery $subject
     * @param callable $proceed
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function aroundResolve(
        BrandCategoriesQuery $subject,
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
