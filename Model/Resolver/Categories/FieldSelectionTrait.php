<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\Categories;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Trait for handling field selection in GraphQL resolvers
 *
 * This trait provides utility methods for checking if fields are requested
 * in a GraphQL query and conditionally adding fields to the result data.
 */
trait FieldSelectionTrait
{
    /**
     * Check if a field is requested in the GraphQL query
     *
     * @param ResolveInfo $info GraphQL resolve info
     * @param string $field Field name to check
     * @return bool True if field is requested
     */
    protected function isFieldRequested(ResolveInfo $info, string $field): bool
    {
        $selections = $info->getFieldSelection(1);
        return isset($selections[$field]);
    }

    /**
     * Add field to data if requested in the GraphQL query
     *
     * @param array $data Data array to add field to
     * @param ResolveInfo $info GraphQL resolve info
     * @param string $field Field name to check and add
     * @param mixed $value Value to add if field is requested
     * @return array Updated data array
     */
    protected function addFieldIfRequested(array $data, ResolveInfo $info, string $field, $value): array
    {
        if ($this->isFieldRequested($info, $field)) {
            $data[$field] = $value;
        }
        return $data;
    }
}
