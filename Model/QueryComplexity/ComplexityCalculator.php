<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\QueryComplexity;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;

class ComplexityCalculator
{
    /**
     * Default complexity for fields
     */
    private const DEFAULT_COMPLEXITY = 1;

    /**
     * Complexity multiplier for list fields
     */
    private const LIST_MULTIPLIER = 10;

    /**
     * Field complexity configurations
     */
    private array $fieldComplexityMap = [
        'products' => [
            'base' => 3,
            'multiplier' => 2,
            'attributes' => [
                'description' => 2,
                'media_gallery' => 3,
                'price_range' => 2,
                'categories' => 2
            ]
        ],
        'categories' => [
            'base' => 2,
            'multiplier' => 1.5,
            'attributes' => [
                'children' => 3,
                'products' => 5
            ]
        ],
        'brandCategories' => [
            'base' => 2,
            'multiplier' => 1.5
        ],
        'cart' => [
            'base' => 3,
            'attributes' => [
                'items' => 2,
                'prices' => 2,
                'shipping_addresses' => 2
            ]
        ]
    ];

    /**
     * Calculate query complexity
     *
     * @param  ResolveInfo $info
     * @return int
     */
    public function calculateComplexity(ResolveInfo $info): int
    {
        $complexity = 0;
        $operation = $info->operation;

        foreach ($operation->selectionSet->selections as $selection) {
            $complexity += $this->calculateSelectionComplexity($selection, $info);
        }

        return $complexity;
    }

    /**
     * Calculate complexity for a selection
     *
     * @param  \GraphQL\Language\AST\SelectionNode $selection
     * @param  ResolveInfo                         $info
     * @param  int                                 $depth
     * @return int
     */
    private function calculateSelectionComplexity($selection, ResolveInfo $info, int $depth = 0): int
    {
        if ($depth > 10) {
            // Prevent infinite recursion
            return 0;
        }

        if ($selection->kind === 'Field') {
            $fieldName = $selection->name->value;
            $complexity = $this->getFieldBaseComplexity($fieldName);

            // Add complexity for arguments (e.g., pagination)
            if (isset($selection->arguments)) {
                foreach ($selection->arguments as $argument) {
                    $complexity += $this->getArgumentComplexity($argument);
                }
            }

            // Calculate complexity for nested selections
            if (isset($selection->selectionSet)) {
                foreach ($selection->selectionSet->selections as $subSelection) {
                    $subComplexity = $this->calculateSelectionComplexity($subSelection, $info, $depth + 1);

                    // Apply multiplier for list fields
                    if ($this->isListField($fieldName, $info)) {
                        $subComplexity *= self::LIST_MULTIPLIER;
                    }

                    $complexity += $subComplexity;
                }
            }

            return $complexity;
        }

        return self::DEFAULT_COMPLEXITY;
    }

    /**
     * Get base complexity for a field
     *
     * @param  string $fieldName
     * @return int
     */
    private function getFieldBaseComplexity(string $fieldName): int
    {
        if (isset($this->fieldComplexityMap[$fieldName])) {
            return $this->fieldComplexityMap[$fieldName]['base'];
        }

        foreach ($this->fieldComplexityMap as $config) {
            if (isset($config['attributes'][$fieldName])) {
                return $config['attributes'][$fieldName];
            }
        }

        return self::DEFAULT_COMPLEXITY;
    }

    /**
     * Calculate complexity added by arguments
     *
     * @param  \GraphQL\Language\AST\ArgumentNode $argument
     * @return int
     */
    private function getArgumentComplexity($argument): int
    {
        $complexity = 0;
        $argName = $argument->name->value;
        $argValue = $argument->value;

        // Add complexity for pagination
        if ($argName === 'pageSize') {
            $value = $this->getArgumentValue($argValue);
            $complexity += min((int)$value / 10, 10); // Cap at 10
        }

        // Add complexity for filters
        if ($argName === 'filter' || $argName === 'filters') {
            $complexity += 2;
        }

        // Add complexity for search
        if ($argName === 'search') {
            $complexity += 3;
        }

        return $complexity;
    }

    /**
     * Get argument value
     *
     * @param  \GraphQL\Language\AST\ValueNode $value
     * @return mixed
     */
    private function getArgumentValue($value)
    {
        switch ($value->kind) {
            case 'IntValue':
            case 'FloatValue':
                return $value->value;
            case 'ListValue':
                return count($value->values);
            case 'ObjectValue':
                return count($value->fields);
            default:
                return 1;
        }
    }

    /**
     * Check if field returns a list
     *
     * @param  string      $fieldName
     * @param  ResolveInfo $info
     * @return bool
     */
    private function isListField(string $fieldName, ResolveInfo $info): bool
    {
        $type = $info->parentType;
        if ($type instanceof ObjectType) {
            $field = $type->getField($fieldName);
            if ($field) {
                $type = $field->getType();
                while ($type instanceof Type) {
                    if ($type instanceof ListOfType) {
                        return true;
                    }
                    $type = method_exists($type, 'getWrappedType') ? $type->getWrappedType() : null;
                }
            }
        }
        return false;
    }
}
