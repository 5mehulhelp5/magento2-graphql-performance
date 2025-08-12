<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;

/**
 * Observer that runs after GraphQL resolver execution
 *
 * This observer stops timing the resolver execution and records the performance
 * metrics, helping track resolver execution times and identify slow resolvers.
 */
class AfterResolver implements ObserverInterface
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
        $this->queryTimer->stop(
            $resolverInfo->operation->name->value,
            $resolverInfo->operation->loc->source->body
        );
    }
}
