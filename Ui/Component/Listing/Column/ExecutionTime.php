<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Ui\Component\Listing\Column;

class ExecutionTime extends AbstractMetricColumn
{
    /**
     * Format execution time
     *
     * @param  mixed $value
     * @return string
     */
    protected function formatValue($value): string
    {
        return sprintf('%.2f ms', (float)$value);
    }
}
