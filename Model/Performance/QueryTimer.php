<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Performance;

use Psr\Log\LoggerInterface;

class QueryTimer
{
    /**
     * @var array Active timers for tracking query execution
     */
    private array $timers = [];

    /**
     * @var array Results of completed query timings
     */
    private array $results = [];

    /**
     * @var array Performance metrics aggregated from query timings
     */
    private array $metrics = [
        'query_count' => 0,
        'total_time' => 0.0,
        'slow_queries' => 0,
        'errors' => 0,
        'total_queries' => 0,
        'cached_queries' => 0
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $slowQueryThreshold = 1000 // milliseconds
    ) {}

    /**
     * Start timing a query
     *
     * @param string $operationName
     * @param string $query
     * @return void
     */
    public function start(string $operationName, string $query): void
    {
        $key = $this->getKey($operationName, $query);
        $this->timers[$key] = [
            'start' => microtime(true),
            'operation' => $operationName,
            'query' => $query
        ];
    }

    /**
     * Stop timing a query and record results
     *
     * @param string $operationName
     * @param string $query
     * @param bool $cached
     * @return void
     */
    public function stop(string $operationName, string $query, bool $cached = false): void
    {
        $key = $this->getKey($operationName, $query);
        if (!isset($this->timers[$key])) {
            return;
        }

        $duration = microtime(true) - $this->timers[$key]['start'];
        $this->results[] = [
            'operation' => $operationName,
            'query' => $query,
            'duration' => $duration,
            'cached' => $cached
        ];

        $this->updateMetrics($duration, $cached);

        // Log if query exceeds threshold
        if ($duration * 1000 > $this->slowQueryThreshold) {
            $this->logger->warning('Slow GraphQL query detected', [
                'operation' => $operationName,
                'duration' => $duration,
                'threshold' => $this->slowQueryThreshold / 1000,
                'cached' => $cached,
                'query' => $query
            ]);
        }

        unset($this->timers[$key]);
    }

    /**
     * Get timing results
     *
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get performance metrics
     *
     * @return array
     */
    public function getMetrics(): array
    {
        $totalQueries = $this->metrics['total_queries'];
        if ($totalQueries === 0) {
            return [
                'query_count' => 0,
                'average_response_time' => 0.0,
                'cache_hit_rate' => 0.0,
                'error_rate' => 0.0,
                'slow_queries' => 0
            ];
        }

        return [
            'query_count' => $totalQueries,
            'average_response_time' => $this->metrics['total_time'] / $totalQueries,
            'cache_hit_rate' => $this->metrics['cached_queries'] / $totalQueries,
            'error_rate' => $this->metrics['errors'] / $totalQueries,
            'slow_queries' => $this->metrics['slow_queries']
        ];
    }

    /**
     * Record an error
     *
     * @return void
     */
    public function recordError(): void
    {
        $this->metrics['errors']++;
    }

    /**
     * Generate unique key for operation/query combination
     *
     * @param string $operationName
     * @param string $query
     * @return string
     */
    private function getKey(string $operationName, string $query): string
    {
        return hash('sha256', $operationName . $query);
    }

    /**
     * Update metrics when query completes
     *
     * @param float $duration Duration in seconds
     * @param bool $cached Whether result was from cache
     * @return void
     */
    private function updateMetrics(float $duration, bool $cached): void
    {
        $this->metrics['total_queries']++;
        $this->metrics['total_time'] += $duration;

        if ($cached) {
            $this->metrics['cached_queries']++;
        }

        // Convert duration to milliseconds for threshold comparison
        if ($duration * 1000 > $this->slowQueryThreshold) {
            $this->metrics['slow_queries']++;
        }
    }
}
