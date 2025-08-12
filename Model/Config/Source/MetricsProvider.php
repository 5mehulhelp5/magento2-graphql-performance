<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for metrics provider configuration
 */
class MetricsProvider implements OptionSourceInterface
{
    /**
     * Get options array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'database', 'label' => __('Database')],
            ['value' => 'redis', 'label' => __('Redis')],
            ['value' => 'elasticsearch', 'label' => __('Elasticsearch')],
            ['value' => 'file', 'label' => __('File System')]
        ];
    }
}
