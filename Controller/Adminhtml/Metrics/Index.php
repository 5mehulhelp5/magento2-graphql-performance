<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Controller\Adminhtml\Metrics;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Admin controller for displaying GraphQL performance metrics
 *
 * This controller handles the admin page that displays performance metrics
 * for GraphQL operations, including response times, cache hit rates, and
 * other performance indicators.
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'Sterk_GraphQlPerformance::metrics';

    /**
     * @param Context $context Admin context
     * @param PageFactory $resultPageFactory Result page factory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Sterk_GraphQlPerformance::metrics');
        $resultPage->getConfig()->getTitle()->prepend(__('GraphQL Performance Metrics'));

        return $resultPage;
    }
}
