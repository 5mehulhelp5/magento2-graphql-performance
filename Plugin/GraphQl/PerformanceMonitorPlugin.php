<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
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
        $this->queryTimer->start($operationName ?? 'anonymous', $source);

        try {
            $result = $proceed($source, $operationName, $variables, $extensions);
            $this->queryTimer->stop($operationName ?? 'anonymous', $source);
            return $result;
        } catch (\Exception $e) {
            $this->queryTimer->stop($operationName ?? 'anonymous', $source);
            throw $e;
        }
    }
}
