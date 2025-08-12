<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Block\Adminhtml\Metrics\Button;

use Magento\Framework\UrlInterface;

/**
 * Button to refresh GraphQL performance metrics
 *
 * This block renders a button in the admin interface that allows users to
 * manually refresh the GraphQL performance metrics. It provides a simple way
 * to update the metrics data without waiting for the automatic refresh.
 */
class RefreshButton extends AbstractButton
{
    /**
     * @param UrlInterface $urlBuilder URL builder service
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Get button label
     *
     * @return string
     */
    protected function getLabel(): string
    {
        return __('Refresh Metrics');
    }

    /**
     * Get button class
     *
     * @return string
     */
    protected function getClass(): string
    {
        return 'primary';
    }

    /**
     * Get on click action
     *
     * @return string
     */
    protected function getOnClick(): string
    {
        return sprintf(
            "location.href = '%s'",
            $this->urlBuilder->getUrl('graphql_performance/metrics/refresh')
        );
    }

    /**
     * Get sort order
     *
     * @return int
     */
    protected function getSortOrder(): int
    {
        return 10;
    }
}
