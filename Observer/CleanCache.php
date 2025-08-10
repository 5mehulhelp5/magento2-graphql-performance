<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

class CleanCache implements ObserverInterface
{
    public function __construct(
        private readonly ResolverCache $cache
    ) {}

    public function execute(Observer $observer): void
    {
        $tags = $observer->getData('tags');
        if (!empty($tags)) {
            $this->cache->clean($tags);
        }
    }
}
