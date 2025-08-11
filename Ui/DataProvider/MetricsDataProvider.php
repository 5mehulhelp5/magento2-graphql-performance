<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Sterk\GraphQlPerformance\Api\PerformanceMetricsInterface;

class MetricsDataProvider extends DataProvider
{
    private array $filters = [];

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly PerformanceMetricsInterface $performanceMetrics,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

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

    public function addFilter(Filter $filter)
    {
        $this->filters[] = [
            'field' => $filter->getField(),
            'value' => $filter->getValue(),
            'condition' => $filter->getConditionType()
        ];
    }

    private function applyFilters(array $items): array
    {
        if (empty($this->filters)) {
            return $items;
        }

        return array_filter(
            $items, function ($item) {
                foreach ($this->filters as $filter) {
                    if (!$this->matchesFilter($item, $filter)) {
                        return false;
                    }
                }
                return true;
            }
        );
    }

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
