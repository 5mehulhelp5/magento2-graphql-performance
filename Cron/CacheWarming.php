<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\Config;
use Magento\Framework\GraphQl\Query\QueryProcessor;
use Psr\Log\LoggerInterface;

class CacheWarming extends AbstractCron
{
    public function __construct(
        private readonly Config $config,
        private readonly QueryProcessor $queryProcessor,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function process(): void
    {
        if (!$this->config->isCacheWarmingEnabled()) {
            return;
        }

        $patterns = $this->config->getCacheWarmingPatterns();
        foreach ($patterns as $name => $query) {
            $this->warmPattern($name, $query);
        }
    }

    private function warmPattern(string $name, string $query): void
    {
        try {
            $this->queryProcessor->process(
                $query,
                [],
                ['store_id' => $this->config->getDefaultStoreId()]
            );
            $this->logger->info(sprintf('Cache warming completed for pattern: %s', $name));
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Cache warming failed for pattern %s: %s', $name, $e->getMessage()));
        }
    }
}
