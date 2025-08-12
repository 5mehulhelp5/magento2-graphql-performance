<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Sterk\GraphQlPerformance\Api\PerformanceMetricsInterface;

/**
 * Data provider for GraphQL performance metrics grid
 */
class MetricsDataProvider extends DataProvider
{
    /**
     * @var array Array of applied filters
     */
    private array $filters = [];

    /**
     * @param string $name Component name
     * @param string $primaryFieldName Primary field name
     * @param string $requestFieldName Request field name
     * @param ReportingInterface $reporting Reporting interface
     * @param SearchCriteriaBuilder $searchCriteriaBuilder Search criteria builder
     * @param RequestInterface $request Request object
     * @param FilterBuilder $filterBuilder Filter builder
     * @param PerformanceMetricsInterface $performanceMetrics Performance metrics service
     * @param array $meta Component meta data
     * @param array $data Additional data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        private readonly PerformanceMetricsInterface $performanceMetrics,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
    }

    /**
     * Get metrics data for grid
     *
     * @return array
     */
    public function getData()
    {
        $metrics = $this->performanceMetrics->getMetrics();

        // Transform metrics into grid format
        $items = $this->transformMetricsToGridData($metrics);

        // Apply filters
        $filteredItems = $this->applyFilters($items);

        return [
            'totalRecords' => count($filteredItems),
            'items' => $filteredItems
        ];
    }

    /**
     * Transform raw metrics data into grid format
     *
     * @param array $metrics Raw metrics data
     * @return array Formatted grid data
     */
    private function transformMetricsToGridData(array $metrics): array
    {
        $items = [];
        $id = 1;

        // Transform overall metrics
        $items[] = [
            'id' => $id++,
            'query_name' => 'Overall Statistics',
            'execution_time' => $metrics['average_response_time'] ?? 0,
            'cache_hit_rate' => $metrics['cache_hit_rate'] ?? 0,
            'error_rate' => $metrics['error_rate'] ?? 0,
            'memory_usage' => $metrics['memory_usage']['current_usage'] ?? 0,
            'last_execution' => date('Y-m-d H:i:s'),
            'is_overall' => true
        ];

        // Transform individual query metrics if available
        if (isset($metrics['queries']) && is_array($metrics['queries'])) {
            foreach ($metrics['queries'] as $queryName => $queryMetrics) {
                $items[] = [
                    'id' => $id++,
                    'query_name' => $queryName,
                    'execution_time' => $queryMetrics['average_response_time'] ?? 0,
                    'cache_hit_rate' => $queryMetrics['cache_hit_rate'] ?? 0,
                    'error_rate' => $queryMetrics['error_rate'] ?? 0,
                    'memory_usage' => $queryMetrics['memory_usage'] ?? 0,
                    'last_execution' => $queryMetrics['last_execution'] ?? date('Y-m-d H:i:s'),
                    'is_overall' => false
                ];
            }
        }

        return $items;
    }

    /**
     * Add filter to the collection
     *
     * @param Filter $filter Filter to add
     */
    public function addFilter(Filter $filter)
    {
        $this->filters[] = [
            'field' => $filter->getField(),
            'value' => $filter->getValue(),
            'condition' => $filter->getConditionType()
        ];
    }

    /**
     * Apply filters to items
     *
     * @param array $items Items to filter
     * @return array Filtered items
     */
    private function applyFilters(array $items): array
    {
        if (empty($this->filters)) {
            return $items;
        }

        return array_filter(
            $items,
            function ($item) {
                foreach ($this->filters as $filter) {
                    if (!$this->matchesFilter($item, $filter)) {
                        return false;
                    }
                }
                return true;
            }
        );
    }

    /**
     * Check if item matches filter
     *
     * @param array $item Item to check
     * @param array $filter Filter to apply
     * @return bool True if item matches filter
     */
    private function matchesFilter(array $item, array $filter): bool
    {
        $value = $item[$filter['field']] ?? null;
        if ($value === null) {
            return false;
        }

        switch ($filter['condition']) {
            case 'eq':
                return $value == $filter['value'];
            case 'neq':
                return $value != $filter['value'];
            case 'like':
                return stripos($value, trim($filter['value'], '%')) !== false;
            case 'gt':
                return $value > $filter['value'];
            case 'lt':
                return $value < $filter['value'];
            default:
                return false;
        }
    }
}
