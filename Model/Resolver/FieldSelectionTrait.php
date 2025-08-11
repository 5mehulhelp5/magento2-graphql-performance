<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

trait FieldSelectionTrait
{
    /**
     * Check if field is requested in GraphQL query
     *
     * @param  ResolveInfo $info
     * @param  string      $field
     * @return bool
     */
    protected function isFieldRequested(ResolveInfo $info, string $field): bool
    {
        $fields = $info->getFieldSelection();
        return isset($fields[$field]);
    }

    /**
     * Get requested fields from GraphQL query
     *
     * @param  ResolveInfo $info
     * @param  array       $defaultFields
     * @return array
     */
    protected function getRequestedFields(ResolveInfo $info, array $defaultFields = []): array
    {
        $fields = $info->getFieldSelection();
        return array_merge($defaultFields, array_keys($fields));
    }

    /**
     * Add field to result if requested
     *
     * @param  array       $result
     * @param  ResolveInfo $info
     * @param  string      $field
     * @param  mixed       $value
     * @return array
     */
    protected function addFieldIfRequested(array $result, ResolveInfo $info, string $field, $value): array
    {
        if ($this->isFieldRequested($info, $field)) {
            $result[$field] = $value;
        }
        return $result;
    }
}
