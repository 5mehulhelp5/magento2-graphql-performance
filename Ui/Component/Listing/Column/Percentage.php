<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Ui\Component\Listing\Column;

class Percentage extends AbstractMetricColumn
{
    /**
     * Format percentage value
     *
     * @param mixed $value
     * @return string
     */
    protected function formatValue($value): string
    {
        return sprintf('%.1f%%', (float)$value * 100);
    }
}
