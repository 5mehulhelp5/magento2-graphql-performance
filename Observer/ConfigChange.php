<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\Cache\CacheWarmer;

/**
 * Observer for handling configuration changes
 *
 * This observer listens for configuration changes and manages the cache accordingly,
 * cleaning the cache when necessary and re-warming it to ensure optimal performance.
 */
class ConfigChange implements ObserverInterface
{
    /**
     * @param ResolverCache $cache Resolver cache instance
     * @param CacheWarmer $cacheWarmer Cache warming service
     */
    public function __construct(
        private readonly ResolverCache $cache,
        private readonly CacheWarmer $cacheWarmer
    ) {
    }

    /**
     * Execute observer logic
     *
     * @param Observer $observer Event observer
     */
    public function execute(Observer $observer): void
    {
        // Clean cache when configuration changes
        $this->cache->clean(['config_change']);

        // Re-warm cache if warming is enabled
        $this->cacheWarmer->warmupCache();
    }
}
