<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Sterk\GraphQlPerformance\Api\CacheManagementInterface;

/**
 * Console command for warming GraphQL performance cache
 *
 * This command executes predefined GraphQL queries to populate the cache with
 * frequently accessed data, improving response times for subsequent requests.
 */
class CacheWarmCommand extends Command
{
    /**
     * @param CacheManagementInterface $cacheManagement Cache management service
     * @param string|null $name Command name
     */
    public function __construct(
        private readonly CacheManagementInterface $cacheManagement,
        string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Configure command options
     */
    protected function configure(): void
    {
        $this->setName('graphql:cache:warm')
            ->setDescription('Warm GraphQL Performance cache');

        parent::configure();
    }

    /**
     * Execute command
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     * @return int Command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln('<info>Warming GraphQL Performance cache...</info>');

            $result = $this->cacheManagement->warm();

            if ($result) {
                $output->writeln('<info>Cache warmed successfully.</info>');
                return Command::SUCCESS;
            }

            $output->writeln('<error>Failed to warm cache.</error>');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
