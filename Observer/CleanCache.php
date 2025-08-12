<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

/**
 * Observer for cleaning GraphQL resolver cache
 *
 * This observer listens for cache cleaning events and cleans the resolver cache
 * when relevant cache tags are invalidated, ensuring data consistency.
 */
class CleanCache implements ObserverInterface
{
    /**
     * @param ResolverCache $cache Resolver cache instance
     */
    public function __construct(
        private readonly ResolverCache $cache
    ) {
    }

    /**
     * Execute observer logic
     *
     * @param Observer $observer Event observer containing cache tags to clean
     */
    public function execute(Observer $observer): void
    {
        $tags = $observer->getData('tags');
        if (!empty($tags)) {
            $this->cache->clean($tags);
        }
    }
}
