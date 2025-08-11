<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;

class AfterResolver implements ObserverInterface
{
    public function __construct(
        private readonly QueryTimer $queryTimer
    ) {
    }

    public function execute(Observer $observer): void
    {
        $resolverInfo = $observer->getData('resolver_info');
        $this->queryTimer->stop(
            $resolverInfo->operation->name->value,
            $resolverInfo->operation->loc->source->body
        );
    }
}
