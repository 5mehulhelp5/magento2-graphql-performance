<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Performance;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;

class FieldUsageAnalyzer
{
    /**
     * @var array Map of field names to their usage counts
     */
    private array $fieldUsageMap = [];

    /**
     * @var array List of field names that are required in all queries
     */
    private array $requiredFields = ['id', 'uid', 'entity_id', 'sku'];

    /**
     * Analyze field usage in query
     *
     * @param Node $ast
     * @return void
     */
    public function analyze(Node $ast): void
    {
        Visitor::visit($ast, [
            'enter' => function (Node $node) {
                if ($node->kind === NodeKind::FIELD) {
                    $fieldName = $node->name->value;
                    $this->fieldUsageMap[$fieldName] = ($this->fieldUsageMap[$fieldName] ?? 0) + 1;
                }
            }
        ]);
    }

    /**
     * Check if field is required
     *
     * @param string $fieldName
     * @return bool
     */
    public function isRequiredField(string $fieldName): bool
    {
        return in_array($fieldName, $this->requiredFields);
    }

    /**
     * Get field usage count
     *
     * @param string $fieldName
     * @return int
     */
    public function getFieldUsageCount(string $fieldName): int
    {
        return $this->fieldUsageMap[$fieldName] ?? 0;
    }

    /**
     * Add required field
     *
     * @param string $fieldName
     * @return void
     */
    public function addRequiredField(string $fieldName): void
    {
        if (!in_array($fieldName, $this->requiredFields)) {
            $this->requiredFields[] = $fieldName;
        }
    }

    /**
     * Reset field usage map
     *
     * @return void
     */
    public function reset(): void
    {
        $this->fieldUsageMap = [];
    }

    /**
     * Get field usage map
     *
     * @return array
     */
    public function getFieldUsageMap(): array
    {
        return $this->fieldUsageMap;
    }
}
