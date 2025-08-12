<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Performance;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;

/**
 * Service class for calculating GraphQL query complexity
 *
 * This class analyzes GraphQL queries to determine their computational
 * complexity. It considers field nesting, argument types, and selection
 * sets to provide a numerical complexity score that can be used for
 * query cost analysis and limiting.
 */
class ComplexityCalculator
{
    /**
     * @var array<string, int> Map of field names to their calculated complexity
     */
    private array $complexityMap = [];

    /**
     * Reset complexity map
     */
    public function reset(): void
    {
        $this->complexityMap = [];
    }

    /**
     * Calculate field complexity
     *
     * @param  Node $node
     * @return int
     */
    public function calculate(Node $node): int
    {
        $fieldName = $node->name->value;

        if (isset($this->complexityMap[$fieldName])) {
            return $this->complexityMap[$fieldName];
        }

        $complexity = $this->calculateBaseComplexity($node);
        $complexity += $this->calculateArgumentsComplexity($node);
        $complexity += $this->calculateSelectionsComplexity($node);

        $this->complexityMap[$fieldName] = $complexity;
        return $complexity;
    }

    /**
     * Calculate base complexity
     *
     * @param  Node $node
     * @return int
     */
    private function calculateBaseComplexity(Node $node): int
    {
        // Base complexity for each field
        return 1;
    }

    /**
     * Calculate arguments complexity
     *
     * @param  Node $node
     * @return int
     */
    private function calculateArgumentsComplexity(Node $node): int
    {
        if (!isset($node->arguments)) {
            return 0;
        }

        $complexity = 0;
        foreach ($node->arguments as $argument) {
            // Add complexity based on argument type
            $complexity += match ($argument->value->kind) {
                NodeKind::LIST => 2, // Lists are more complex
                NodeKind::OBJECT => 3, // Objects are most complex
                default => 1 // Simple values
            };
        }

        return $complexity;
    }

    /**
     * Calculate selections complexity
     *
     * @param  Node $node
     * @return int
     */
    private function calculateSelectionsComplexity(Node $node): int
    {
        if (!isset($node->selectionSet)) {
            return 0;
        }

        $complexity = 0;
        foreach ($node->selectionSet->selections as $selection) {
            if ($selection->kind === NodeKind::FIELD) {
                $complexity += $this->calculate($selection);
            }
        }

        return $complexity;
    }

    /**
     * Get complexity map
     *
     * @return array
     */
    public function getComplexityMap(): array
    {
        return $this->complexityMap;
    }
}
