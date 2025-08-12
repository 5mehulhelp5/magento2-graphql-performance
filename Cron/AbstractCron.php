<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Cron;

use Psr\Log\LoggerInterface;

/**
 * Abstract base class for GraphQL performance cron jobs
 *
 * This class provides common functionality for cron jobs including error handling,
 * logging, and execution flow. All GraphQL performance cron jobs should extend
 * this class and implement the process() method.
 */
abstract class AbstractCron
{
    /**
     * @param LoggerInterface $logger Logger for recording cron job execution
     */
    public function __construct(
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->process();
            $this->logSuccess();
        } catch (\Exception $e) {
            $this->logError($e);
        }
    }

    /**
     * Process cron job
     *
     * @return void
     */
    abstract protected function process(): void;

    /**
     * Log success message
     *
     * @return void
     */
    protected function logSuccess(): void
    {
        $this->logger->info(sprintf(
            '%s completed successfully',
            $this->getCronName()
        ));
    }

    /**
     * Log error message
     *
     * @param \Exception $e
     * @return void
     */
    protected function logError(\Exception $e): void
    {
        $this->logger->error(sprintf(
            '%s failed: %s',
            $this->getCronName(),
            $e->getMessage()
        ));
    }

    /**
     * Get cron name
     *
     * @return string
     */
    protected function getCronName(): string
    {
        return 'GraphQL ' . (new \ReflectionClass($this))->getShortName();
    }
}
