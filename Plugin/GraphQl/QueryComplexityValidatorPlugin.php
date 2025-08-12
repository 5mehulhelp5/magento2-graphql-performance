<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema;
use Sterk\GraphQlPerformance\Model\QueryComplexity\ComplexityValidator;

/**
 * Plugin for validating GraphQL query complexity
 *
 * This plugin analyzes GraphQL queries before execution to ensure they don't
 * exceed configured complexity limits, preventing resource-intensive queries
 * that could impact performance.
 */
class QueryComplexityValidatorPlugin
{
    /**
     * @param ComplexityValidator $complexityValidator Service for validating query complexity
     */
    public function __construct(
        private readonly ComplexityValidator $complexityValidator
    ) {
    }

    /**
     * Validate query complexity before processing
     *
     * @param QueryProcessor $subject Query processor instance
     * @param \Closure $proceed Original method
     * @param string $source GraphQL query source
     * @param string|null $operationName Operation name
     * @param array|null $variables Query variables
     * @param array|null $extensions GraphQL extensions
     * @return array
     */
    public function aroundProcess(
        QueryProcessor $subject,
        \Closure $proceed,
        string $source,
        ?string $operationName = null,
        ?array $variables = null,
        ?array $extensions = null
    ): array {
        // Parse the query to get AST
        $documentNode = \GraphQL\Language\Parser::parse(new \GraphQL\Language\Source($source));

        // Get operation
        $operation = null;
        foreach ($documentNode->definitions as $definition) {
            if ($definition->kind === 'OperationDefinition') {
                if ($operationName === null || $definition->name->value === $operationName) {
                    $operation = $definition;
                    break;
                }
            }
        }

        if ($operation) {
            // Create ResolveInfo
            $info = new \Magento\Framework\GraphQl\Query\Resolver\ResolveInfo(
                $operation->name ? $operation->name->value : null,
                [],
                $schema->getType('Query'),
                $documentNode
            );

            // Validate complexity
            $this->complexityValidator->validate($info);
        }

        // Proceed with query execution
        return $proceed($source, $operationName, $variables, $extensions);
    }
}
