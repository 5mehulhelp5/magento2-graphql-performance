<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class MetricsActions extends Column
{
    private const URL_PATH_VIEW = 'graphql_performance/metrics/view';
    private const URL_PATH_DELETE = 'graphql_performance/metrics/delete';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['id'])) {
                    $item[$this->getData('name')] = $this->getActions($item);
                }
            }
        }

        return $dataSource;
    }

    /**
     * Get actions array
     *
     * @param array $item
     * @return array
     */
    private function getActions(array $item): array
    {
        return [
            'view' => [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_VIEW, ['id' => $item['id']]),
                'label' => __('View Details')
            ],
            'delete' => [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_DELETE, ['id' => $item['id']]),
                'label' => __('Delete'),
                'confirm' => [
                    'title' => __('Delete Metric'),
                    'message' => __('Are you sure you want to delete this metric?')
                ]
            ]
        ];
    }
}
