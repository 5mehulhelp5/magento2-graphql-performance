<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\TypeResolver;

use Magento\Framework\GraphQl\Query\Resolver\TypeResolverInterface;

/**
 * Type resolver for BatchLoadableField interface
 */
class BatchLoadableFieldResolver implements TypeResolverInterface
{
    /**
     * Determine concrete type for BatchLoadableField interface
     *
     * @param array $data The data to resolve type from
     * @return string
     */
    public function resolveType(array $data): string
    {
        // Since this is an interface, we need to determine the concrete type
        // based on the data. For now, we'll return a default type.
        return 'BatchStats';
    }
}
