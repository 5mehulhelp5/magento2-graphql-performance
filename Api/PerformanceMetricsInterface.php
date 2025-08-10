<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Api;

interface PerformanceMetricsInterface
{
    /**
     * Get performance metrics
     *
     * @return array
     * @api
     */
    public function getMetrics(): array;
}
