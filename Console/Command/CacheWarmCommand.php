<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Sterk\GraphQlPerformance\Api\CacheManagementInterface;

class CacheWarmCommand extends Command
{
    public function __construct(
        private readonly CacheManagementInterface $cacheManagement,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('graphql:cache:warm')
            ->setDescription('Warm GraphQL Performance cache');

        parent::configure();
    }

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
