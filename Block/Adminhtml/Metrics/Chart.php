<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Block\Adminhtml\Metrics;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Sterk\GraphQlPerformance\Api\PerformanceMetricsInterface;

/**
 * Block class for displaying GraphQL performance metrics chart
 */
class Chart extends Template
{
    /**
     * @param Context $context Block context
     * @param PerformanceMetricsInterface $performanceMetrics Performance metrics service
     * @param array $data Additional block data
     */
    public function __construct(
        Context $context,
        private readonly PerformanceMetricsInterface $performanceMetrics,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get raw performance metrics data
     *
     * @return array
     */
    public function getMetrics(): array
    {
        return $this->performanceMetrics->getMetrics();
    }

    /**
     * Get formatted data for chart display
     *
     * @return array{
     *     labels: array<string>,
     *     responseTimes: array<float>,
     *     cacheHitRates: array<float>
     * }
     */
    public function getChartData(): array
    {
        $metrics = $this->getMetrics();
        $historicalData = $metrics['historical_data'] ?? [];

        if (empty($historicalData)) {
            $historicalData = [[
                'timestamp' => date('H:i'),
                'average_response_time' => $metrics['average_response_time'] ?? 0,
                'cache_hit_rate' => $metrics['cache_hit_rate'] ?? 0
            ]];
        }

        return [
            'labels' => array_column($historicalData, 'timestamp'),
            'responseTimes' => array_column($historicalData, 'average_response_time'),
            'cacheHitRates' => array_map(function ($rate) {
                return $rate * 100;
            }, array_column($historicalData, 'cache_hit_rate'))
        ];
    }

    /**
     * Format a metric value with the given format string
     *
     * @param array $metrics The metrics array
     * @param string $path Path to the metric value
     * @param string $format Format string (default: '%.2f')
     * @param mixed $default Default value if metric not found
     * @return string
     */
    public function getFormattedMetricValue(
        array $metrics,
        string $path,
        string $format = '%.2f',
        mixed $default = 0
    ): string {
        $value = $this->getMetricValue($metrics, $path, $default);
        return sprintf($format, $value);
    }

    /**
     * Get metric value from path
     *
     * @param array $metrics
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    private function getMetricValue(array $metrics, string $path, mixed $default): mixed
    {
        $keys = explode('/', $path);
        $value = $metrics;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
