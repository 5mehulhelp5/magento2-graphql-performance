<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Sterk\GraphQlPerformance\Model\Config;
use Magento\Framework\GraphQl\Query\QueryProcessor;
use Psr\Log\LoggerInterface;

/**
 * Cron job for warming GraphQL cache
 *
 * This cron job runs predefined GraphQL queries to populate the cache with
 * frequently accessed data, improving response times for subsequent requests.
 * It uses configured query patterns to simulate real user queries.
 */
class CacheWarming extends AbstractCron
{
    /**
     * @param Config $config Configuration service
     * @param QueryProcessor $queryProcessor GraphQL query processor
     * @param LoggerInterface $logger Logger for recording cron job execution
     */
    public function __construct(
        private readonly Config $config,
        private readonly QueryProcessor $queryProcessor,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Process cron job by executing cache warming patterns
     */
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

    /**
     * Execute a single cache warming pattern
     *
     * @param string $name Pattern name for logging
     * @param string $query GraphQL query to execute
     */
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
