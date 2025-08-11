<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\Cache\CacheWarmer;

class ConfigChange implements ObserverInterface
{
    public function __construct(
        private readonly ResolverCache $cache,
        private readonly CacheWarmer $cacheWarmer
    ) {
    }

    public function execute(Observer $observer): void
    {
        // Clean cache when configuration changes
        $this->cache->clean(['config_change']);

        // Re-warm cache if warming is enabled
        $this->cacheWarmer->warm();
    }
}
