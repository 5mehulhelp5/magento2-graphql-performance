<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Block\Adminhtml\Metrics\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Abstract base class for admin button components
 *
 * This class provides common functionality for admin UI buttons, including
 * button data structure, default implementations, and required abstract methods
 * that concrete button classes must implement.
 */
abstract class AbstractButton implements ButtonProviderInterface
{
    /**
     * Get button data
     *
     * @return array
     */
    public function getButtonData(): array
    {
        return [
            'label' => $this->getLabel(),
            'class' => $this->getClass(),
            'on_click' => $this->getOnClick(),
            'data_attribute' => $this->getDataAttributes(),
            'sort_order' => $this->getSortOrder()
        ];
    }

    /**
     * Get button label
     *
     * @return string
     */
    abstract protected function getLabel(): string;

    /**
     * Get button class
     *
     * @return string
     */
    abstract protected function getClass(): string;

    /**
     * Get on click action
     *
     * @return string|null
     */
    protected function getOnClick(): ?string
    {
        return null;
    }

    /**
     * Get data attributes
     *
     * @return array
     */
    protected function getDataAttributes(): array
    {
        return [];
    }

    /**
     * Get sort order
     *
     * @return int
     */
    protected function getSortOrder(): int
    {
        return 0;
    }
}
