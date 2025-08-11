<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Query\ResolverInterface;

abstract class AbstractResolverPlugin
{
    public function __construct(
        private readonly ResolverInterface $optimizedResolver
    ) {
    }

    /**
     * Replace the original resolver with our optimized version
     *
     * @param  object      $subject
     * @param  callable    $proceed
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array|null  $value
     * @param  array|null  $args
     * @return array
     */
    public function aroundResolve(
        object $subject,
        callable $proceed,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        return $this->optimizedResolver->resolve($field, $context, $info, $value ?? [], $args ?? []);
    }

    /**
     * Get optimized resolver instance
     *
     * @return ResolverInterface
     */
    protected function getOptimizedResolver(): ResolverInterface
    {
        return $this->optimizedResolver;
    }
}
