<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;

/**
 * Observer that runs before GraphQL resolver execution
 *
 * This observer starts timing the resolver execution, allowing us to track
 * performance metrics for individual resolvers and identify potential bottlenecks.
 */
class BeforeResolver implements ObserverInterface
{
    /**
     * @param QueryTimer $queryTimer Service for measuring resolver execution time
     */
    public function __construct(
        private readonly QueryTimer $queryTimer
    ) {
    }

    /**
     * Execute observer logic
     *
     * @param Observer $observer Event observer
     */
    public function execute(Observer $observer): void
    {
        $resolverInfo = $observer->getData('resolver_info');
        $this->queryTimer->start(
            $resolverInfo->operation->name->value,
            $resolverInfo->operation->loc->source->body
        );
    }
}
