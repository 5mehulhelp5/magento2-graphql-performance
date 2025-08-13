<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
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
     * @param Schema $schema GraphQL schema
     * @param string|null $source GraphQL query source
     * @param ContextInterface|null $context Query context
     * @param array|null $variables Query variables
     * @param string|null $operationName Operation name
     * @param array|null $extensions GraphQL extensions
     * @return array
     */
    public function aroundProcess(
        QueryProcessor $subject,
        \Closure $proceed,
        Schema $schema,
        ?string $source = null,
        ?ContextInterface $context = null,
        ?array $variables = null,
        ?string $operationName = null,
        ?array $extensions = null
    ): array {
        if ($source && !$this->isIntrospectionQuery($source)) {
            // Parse the query to get AST
            $documentNode = Parser::parse(new Source($source));

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
                $info = new ResolveInfo(
                    $operation->name ? $operation->name->value : null,
                    [],
                    $schema->getType('Query'),
                    $documentNode
                );

                // Validate complexity
                $this->complexityValidator->validate($info);
            }
        }

        // Proceed with query execution
        return $proceed($schema, $source, $context, $variables, $operationName, $extensions);
    }

    /**
     * Check if the query is an introspection query
     *
     * @param string $source
     * @return bool
     */
    private function isIntrospectionQuery(string $source): bool
    {
        return str_contains($source, '__schema') || str_contains($source, '__type');
    }
}
