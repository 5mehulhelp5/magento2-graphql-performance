<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;

/**
 * Plugin for monitoring GraphQL query performance
 *
 * This plugin measures and records the execution time and performance metrics
 * of GraphQL queries, providing insights into query performance and helping
 * identify potential bottlenecks.
 */
class PerformanceMonitorPlugin
{
    /**
     * @param QueryTimer $queryTimer Service for measuring query execution time
     */
    public function __construct(
        private readonly QueryTimer $queryTimer
    ) {
    }

    /**
     * Monitor GraphQL query performance
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
        if ($source) {
            $this->queryTimer->start($operationName ?? 'anonymous', $source);
        }

        try {
            $result = $proceed($schema, $source, $context, $variables, $operationName, $extensions);
            if ($source) {
                $this->queryTimer->stop($operationName ?? 'anonymous', $source);
            }
            return $result;
        } catch (\Exception $e) {
            if ($source) {
                $this->queryTimer->stop($operationName ?? 'anonymous', $source);
            }
            throw $e;
        }
    }
}
