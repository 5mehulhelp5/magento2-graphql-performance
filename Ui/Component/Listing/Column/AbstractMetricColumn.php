<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

abstract class AbstractMetricColumn extends Column
{
    /**
     * Format value for display
     *
     * @param  mixed $value
     * @return string
     */
    abstract protected function formatValue($value): string;

    /**
     * Prepare Data Source
     *
     * @param  array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item[$fieldName])) {
                    $item[$fieldName] = $this->formatValue($item[$fieldName]);
                }
            }
        }
        return $dataSource;
    }

    /**
     * Get filter condition type
     *
     * @return string
     */
    protected function getFilterConditionType(): string
    {
        return 'textRange';
    }

    /**
     * Get column settings
     *
     * @return array
     */
    protected function getColumnSettings(): array
    {
        return [
            'filter' => $this->getFilterConditionType(),
            'label' => __($this->getData('config/label'))
        ];
    }
}
