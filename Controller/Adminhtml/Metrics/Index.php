<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Controller\Adminhtml\Metrics;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Sterk_GraphQlPerformance::metrics';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Sterk_GraphQlPerformance::metrics');
        $resultPage->getConfig()->getTitle()->prepend(__('GraphQL Performance Metrics'));

        return $resultPage;
    }
}
