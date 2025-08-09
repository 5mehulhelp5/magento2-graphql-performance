<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\Config;
use Magento\Framework\GraphQl\Query\QueryProcessor;
use Psr\Log\LoggerInterface;

class CacheWarming
{
    public function __construct(
        private readonly Config $config,
        private readonly QueryProcessor $queryProcessor,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(): void
    {
        if (!$this->config->isCacheWarmingEnabled()) {
            return;
        }

        try {
            $patterns = $this->config->getCacheWarmingPatterns();
            foreach ($patterns as $name => $query) {
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
        } catch (\Exception $e) {
            $this->logger->error('GraphQL cache warming failed: ' . $e->getMessage());
        }
    }
}
