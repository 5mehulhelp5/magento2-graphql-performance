<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\QueryComplexity;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class ComplexityValidator
{
    /**
     * Default maximum query complexity
     */
    private const DEFAULT_MAX_COMPLEXITY = 300;

    /**
     * Default maximum query depth
     */
    private const DEFAULT_MAX_DEPTH = 20;

    public function __construct(
        private readonly ComplexityCalculator $complexityCalculator,
        private readonly int $maxComplexity = self::DEFAULT_MAX_COMPLEXITY,
        private readonly int $maxDepth = self::DEFAULT_MAX_DEPTH
    ) {}

    /**
     * Validate query complexity
     *
     * @param ResolveInfo $info
     * @throws GraphQlInputException
     */
    public function validate(ResolveInfo $info): void
    {
        // Calculate query complexity
        $complexity = $this->complexityCalculator->calculateComplexity($info);

        // Check complexity limit
        if ($complexity > $this->maxComplexity) {
            throw new GraphQlInputException(
                __(
                    'Query complexity of %1 exceeds maximum allowed complexity of %2. ' .
                    'Try reducing the number of nested fields or the size of your request.',
                    $complexity,
                    $this->maxComplexity
                )
            );
        }

        // Check query depth
        $depth = $this->calculateQueryDepth($info->operation);
        if ($depth > $this->maxDepth) {
            throw new GraphQlInputException(
                __(
                    'Query depth of %1 exceeds maximum allowed depth of %2. ' .
                    'Try reducing the number of nested levels in your query.',
                    $depth,
                    $this->maxDepth
                )
            );
        }
    }

    /**
     * Calculate query depth
     *
     * @param \GraphQL\Language\AST\OperationDefinitionNode $operation
     * @return int
     */
    private function calculateQueryDepth($operation): int
    {
        return $this->getSelectionSetDepth($operation->selectionSet);
    }

    /**
     * Get selection set depth
     *
     * @param \GraphQL\Language\AST\SelectionSetNode $selectionSet
     * @return int
     */
    private function getSelectionSetDepth($selectionSet): int
    {
        $maxDepth = 0;

        foreach ($selectionSet->selections as $selection) {
            if ($selection->kind === 'Field') {
                $depth = 1;
                if (isset($selection->selectionSet)) {
                    $depth += $this->getSelectionSetDepth($selection->selectionSet);
                }
                $maxDepth = max($maxDepth, $depth);
            }
        }

        return $maxDepth;
    }
}
