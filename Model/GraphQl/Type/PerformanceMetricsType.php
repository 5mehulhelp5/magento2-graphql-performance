<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\GraphQl\Type;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Config\Element\Type;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * GraphQL type for performance metrics
 *
 * This type defines the structure of performance metrics data in the GraphQL
 * schema. It includes fields for query statistics, memory usage, cache stats,
 * and connection pool metrics to provide comprehensive performance monitoring.
 */
class PerformanceMetricsType extends Type
{
    /**
     * @param string $name Type name
     * @param Field[] $fields Type fields
     * @param string[] $interfaces Type interfaces
     * @param string $description Type description
     */
    public function __construct(
        string $name,
        array $fields = [],
        array $interfaces = [],
        string $description = ''
    ) {
        $fields = array_merge([
            'query_count' => new Field([
                'name' => 'query_count',
                'type' => 'Int',
                'description' => 'Total number of queries executed'
            ]),
            'average_response_time' => new Field([
                'name' => 'average_response_time',
                'type' => 'Float',
                'description' => 'Average query response time in milliseconds'
            ]),
            'cache_hit_rate' => new Field([
                'name' => 'cache_hit_rate',
                'type' => 'Float',
                'description' => 'Cache hit rate percentage'
            ]),
            'error_rate' => new Field([
                'name' => 'error_rate',
                'type' => 'Float',
                'description' => 'Query error rate percentage'
            ]),
            'slow_queries' => new Field([
                'name' => 'slow_queries',
                'type' => 'Int',
                'description' => 'Number of slow queries'
            ]),
            'memory_usage' => new Field([
                'name' => 'memory_usage',
                'type' => 'MemoryMetrics',
                'description' => 'Memory usage statistics'
            ]),
            'cache_stats' => new Field([
                'name' => 'cache_stats',
                'type' => 'CacheStats',
                'description' => 'Cache statistics'
            ]),
            'connection_pool_stats' => new Field([
                'name' => 'connection_pool_stats',
                'type' => 'ConnectionPoolStats',
                'description' => 'Database connection pool statistics'
            ])
        ], $fields);

        parent::__construct($name, $fields, $interfaces, $description);
    }

    /**
     * Resolve performance metrics data
     *
     * @param mixed $value Field value
     * @param ContextInterface $context Request context
     * @param ResolveInfo $info Resolve info
     * @param array|null $args Field arguments
     * @return mixed Resolved value
     */
    public function resolve(
        $value,
        $context,
        ResolveInfo $info,
        ?array $args = null
    ) {
        return $value;
    }
}
