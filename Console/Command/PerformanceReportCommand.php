<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Sterk\GraphQlPerformance\Api\PerformanceMetricsInterface;

class PerformanceReportCommand extends Command
{
    private const FORMAT_OPTION = 'format';
    private const PERIOD_OPTION = 'period';

    public function __construct(
        private readonly PerformanceMetricsInterface $performanceMetrics,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('graphql:performance:report')
            ->setDescription('Generate GraphQL Performance report')
            ->addOption(
                self::FORMAT_OPTION,
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format (text, json, csv)',
                'text'
            )
            ->addOption(
                self::PERIOD_OPTION,
                'p',
                InputOption::VALUE_OPTIONAL,
                'Report period in hours',
                '24'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $format = $input->getOption(self::FORMAT_OPTION);
            $metrics = $this->performanceMetrics->getMetrics();

            switch ($format) {
                case 'json':
                    $output->writeln(json_encode($metrics, JSON_PRETTY_PRINT));
                    break;

                case 'csv':
                    $this->outputCsv($output, $metrics);
                    break;

                default:
                    $this->outputText($output, $metrics);
                    break;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function outputText(OutputInterface $output, array $metrics): void
    {
        $output->writeln('<info>GraphQL Performance Report</info>');
        $output->writeln('============================');
        $output->writeln('');

        $output->writeln(sprintf('Query Count: %d', $metrics['query_count'] ?? 0));
        $output->writeln(sprintf('Average Response Time: %.2fms', $metrics['average_response_time'] ?? 0));
        $output->writeln(sprintf('Cache Hit Rate: %.2f%%', ($metrics['cache_hit_rate'] ?? 0) * 100));
        $output->writeln(sprintf('Error Rate: %.2f%%', ($metrics['error_rate'] ?? 0) * 100));
        $output->writeln(sprintf('Slow Queries: %d', $metrics['slow_queries'] ?? 0));

        if (isset($metrics['memory_usage'])) {
            $output->writeln('');
            $output->writeln('Memory Usage:');
            $output->writeln(sprintf('  Current: %.2f MB', $metrics['memory_usage']['current_usage'] ?? 0));
            $output->writeln(sprintf('  Peak: %.2f MB', $metrics['memory_usage']['peak_usage'] ?? 0));
        }

        if (isset($metrics['cache_stats'])) {
            $output->writeln('');
            $output->writeln('Cache Statistics:');
            $output->writeln(sprintf('  Hits: %d', $metrics['cache_stats']['hits'] ?? 0));
            $output->writeln(sprintf('  Misses: %d', $metrics['cache_stats']['misses'] ?? 0));
            $output->writeln(sprintf('  Hit Rate: %.2f%%', ($metrics['cache_stats']['hit_rate'] ?? 0) * 100));
        }
    }

    private function outputCsv(OutputInterface $output, array $metrics): void
    {
        $headers = ['Metric', 'Value'];
        $output->writeln(implode(',', $headers));

        $rows = [
            ['Query Count', $metrics['query_count'] ?? 0],
            ['Average Response Time', $metrics['average_response_time'] ?? 0],
            ['Cache Hit Rate', ($metrics['cache_hit_rate'] ?? 0) * 100],
            ['Error Rate', ($metrics['error_rate'] ?? 0) * 100],
            ['Slow Queries', $metrics['slow_queries'] ?? 0],
            ['Memory Usage (Current)', $metrics['memory_usage']['current_usage'] ?? 0],
            ['Memory Usage (Peak)', $metrics['memory_usage']['peak_usage'] ?? 0],
            ['Cache Hits', $metrics['cache_stats']['hits'] ?? 0],
            ['Cache Misses', $metrics['cache_stats']['misses'] ?? 0],
            ['Cache Hit Rate', ($metrics['cache_stats']['hit_rate'] ?? 0) * 100]
        ];

        foreach ($rows as $row) {
            $output->writeln(implode(',', $row));
        }
    }
}
