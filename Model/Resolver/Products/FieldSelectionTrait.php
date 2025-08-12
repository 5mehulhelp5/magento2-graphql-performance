<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\Products;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

trait FieldSelectionTrait
{
    protected function isFieldRequested(ResolveInfo $info, string $field): bool
    {
        $selections = $info->getFieldSelection(1);
        return isset($selections[$field]);
    }

    protected function addFieldIfRequested(array $data, ResolveInfo $info, string $field, $value): array
    {
        if ($this->isFieldRequested($info, $field)) {
            $data[$field] = $value;
        }
        return $data;
    }
}
