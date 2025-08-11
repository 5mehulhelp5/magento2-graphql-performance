<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Ui\Component\Listing\Column;

class Memory extends AbstractMetricColumn
{
    private const UNITS = ['B', 'KB', 'MB', 'GB'];

    /**
     * Format memory value
     *
     * @param  mixed $value
     * @return string
     */
    protected function formatValue($value): string
    {
        $bytes = (float)$value;
        $exp = floor(log($bytes, 1024));
        $exp = min($exp, count(self::UNITS) - 1);

        return sprintf(
            '%.1f %s',
            $bytes / pow(1024, $exp),
            self::UNITS[$exp]
        );
    }
}
