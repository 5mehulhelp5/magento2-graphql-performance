<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Sterk\GraphQlPerformance\Api\CacheManagementInterface;

class CacheCleanCommand extends Command
{
    private const TAGS_OPTION = 'tags';

    public function __construct(
        private readonly CacheManagementInterface $cacheManagement,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('graphql:cache:clean')
            ->setDescription('Clean GraphQL Performance cache')
            ->addOption(
                self::TAGS_OPTION,
                't',
                InputOption::VALUE_OPTIONAL,
                'Comma-separated list of cache tags to clean'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln('<info>Cleaning GraphQL Performance cache...</info>');

            $result = $this->cacheManagement->clean();

            if ($result) {
                $output->writeln('<info>Cache cleaned successfully.</info>');
                return Command::SUCCESS;
            }

            $output->writeln('<error>Failed to clean cache.</error>');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
