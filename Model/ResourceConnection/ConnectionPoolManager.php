<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\ResourceConnection;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Sterk\GraphQlPerformance\Model\Config;

class ConnectionPoolManager
{
    private array $connections = [
        'active' => [],
        'idle' => []
    ];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Config $config
    ) {}

    /**
     * Get connection from pool
     *
     * @return AdapterInterface
     */
    public function getConnection(): AdapterInterface
    {
        // Try to get an idle connection
        if (!empty($this->connections['idle'])) {
            $connection = array_pop($this->connections['idle']);
            $this->connections['active'][] = $connection;
            return $connection;
        }

        // Create new connection if pool is not full
        $totalConnections = count($this->connections['active']) + count($this->connections['idle']);
        if ($totalConnections < $this->config->getMaxConnections()) {
            $connection = $this->createConnection();
            $this->connections['active'][] = $connection;
            return $connection;
        }

        // Wait for available connection
        return $this->waitForConnection();
    }

    /**
     * Release connection back to pool
     *
     * @param AdapterInterface $connection
     * @return void
     */
    public function releaseConnection(AdapterInterface $connection): void
    {
        $key = array_search($connection, $this->connections['active'], true);
        if ($key !== false) {
            unset($this->connections['active'][$key]);
            $this->connections['idle'][] = $connection;

            // Clean up idle connections if too many
            $this->cleanupIdleConnections();
        }
    }

    /**
     * Create new database connection
     *
     * @return AdapterInterface
     */
    private function createConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    /**
     * Wait for available connection
     *
     * @return AdapterInterface
     */
    private function waitForConnection(): AdapterInterface
    {
        $retryCount = 0;
        $maxRetries = $this->config->getConnectionPoolConfig('retry_limit') ?? 3;
        $retryDelay = $this->config->getConnectionPoolConfig('retry_delay') ?? 1000; // milliseconds

        while ($retryCount < $maxRetries) {
            // Check for released connections
            if (!empty($this->idleConnections)) {
                $connection = array_pop($this->idleConnections);
                $this->activeConnections[] = $connection;
                return $connection;
            }

            // Wait before retry
            usleep($retryDelay * 1000);
            $retryCount++;
        }

        throw new \RuntimeException('No available connections in pool');
    }

    /**
     * Clean up idle connections
     *
     * @return void
     */
    private function cleanupIdleConnections(): void
    {
        $minConnections = $this->config->getMinConnections();
        $totalConnections = count($this->connections['active']) + count($this->connections['idle']);
        $maxIdleConnections = $minConnections - count($this->connections['active']);

        while ($totalConnections > $minConnections &&
               count($this->connections['idle']) > $maxIdleConnections) {
            array_pop($this->connections['idle']);
            $totalConnections--;
        }
    }

    /**
     * Get pool statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'total_connections' => count($this->connections['active']) + count($this->connections['idle']),
            'active_connections' => count($this->connections['active']),
            'idle_connections' => count($this->connections['idle']),
            'max_connections' => $this->config->getMaxConnections(),
            'min_connections' => $this->config->getMinConnections()
        ];
    }

    /**
     * Perform health check on connections
     *
     * @return void
     */
    public function healthCheck(): void
    {
        $this->checkConnections('active');
        $this->checkConnections('idle');
    }

    /**
     * Check connections in a specific pool
     *
     * @param string $poolType
     * @return void
     */
    private function checkConnections(string $poolType): void
    {
        foreach ($this->connections[$poolType] as $key => $connection) {
            try {
                if (!$connection->ping()) {
                    unset($this->connections[$poolType][$key]);
                }
            } catch (\Exception $e) {
                unset($this->connections[$poolType][$key]);
            }
        }
    }
}
