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
     * @param  QueryProcessor $subject
     * @param  callable       $proceed
     * @param  string         $query
     * @param  string|null    $operationName
     * @param  array          $variables
     * @return array
     */
    public function aroundProcess(
        QueryProcessor $subject,
        callable $proceed,
        string $query,
        ?string $operationName = null,
        array $variables = []
    ): array {
        $this->queryTimer->start($operationName ?? 'anonymous', $query);

        try {
            $result = $proceed($query, $operationName, $variables);
            $this->queryTimer->stop($operationName ?? 'anonymous', $query);
            return $result;
        } catch (\Exception $e) {
            $this->queryTimer->stop($operationName ?? 'anonymous', $query);
            throw $e;
        }
    }
}
