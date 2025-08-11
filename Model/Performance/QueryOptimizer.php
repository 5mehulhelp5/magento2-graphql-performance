<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Performance;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Language\Visitor;
use GraphQL\Language\Printer;
use Magento\Framework\GraphQl\Schema;

class QueryOptimizer
{
    public function __construct(
        private readonly Schema $schema,
        private readonly ComplexityCalculator $complexityCalculator,
        private readonly FieldUsageAnalyzer $fieldUsageAnalyzer,
        private readonly CacheDirectiveManager $cacheDirectiveManager
    ) {
    }

    /**
     * Optimize GraphQL query
     *
     * @param  string $query
     * @return string
     */
    public function optimize(string $query): string
    {
        try {
            $ast = Parser::parse($query);

            // Reset analyzers
            $this->complexityCalculator->reset();
            $this->fieldUsageAnalyzer->reset();

            // Analyze query
            $this->fieldUsageAnalyzer->analyze($ast);
            $ast = $this->removeUnusedFields($ast);

            // Add cache directives if needed
            if (!$this->cacheDirectiveManager->hasCacheDirectives($ast)) {
                $ast = $this->cacheDirectiveManager->addCacheDirectives($ast);
            }

            return $this->printAst($ast);
        } catch (\Exception $e) {
            // If optimization fails, return original query
            return $query;
        }
    }

    /**
     * Remove unused fields from query
     *
     * @param  Node $ast
     * @return Node
     */
    private function removeUnusedFields(Node $ast): Node
    {
        return Visitor::visit(
            $ast,
            [
            'leave' => function (Node $node) {
                if ($node->kind === NodeKind::FIELD) {
                    $fieldName = $node->name->value;
                    $usageCount = $this->fieldUsageAnalyzer->getFieldUsageCount($fieldName);

                    if ($usageCount <= 1 && !$this->fieldUsageAnalyzer->isRequiredField($fieldName)) {
                        return null;
                    }
                }
                return $node;
            }
            ]
        );
    }

    /**
     * Print AST as string
     *
     * @param  Node $ast
     * @return string
     */
    private function printAst(Node $ast): string
    {
        return Printer::doPrint($ast);
    }
}
