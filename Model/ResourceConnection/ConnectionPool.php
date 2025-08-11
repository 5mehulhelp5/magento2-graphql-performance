<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\ResourceConnection;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\LoggerInterface;
use Magento\Framework\App\DeploymentConfig;

class ConnectionPool
{
    private array $connections = [];
    private array $activeConnections = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $maxConnections = 50,
        private readonly int $minConnections = 5,
        private readonly int $idleTimeout = 300 // 5 minutes
    ) {}

    /**
     * Get a connection from the pool
     *
     * @param string $connectionName
     * @return AdapterInterface
     */
    public function getConnection(string $connectionName = ResourceConnection::DEFAULT_CONNECTION): AdapterInterface
    {
        $this->cleanupIdleConnections();

        // Try to reuse an existing idle connection
        foreach ($this->connections as $key => $connection) {
            if (!isset($this->activeConnections[$key]) && $connection['name'] === $connectionName) {
                $this->activeConnections[$key] = time();
                return $connection['connection'];
            }
        }

        // Create new connection if under max limit
        if (count($this->connections) < $this->maxConnections) {
            return $this->createNewConnection($connectionName);
        }

        // If at connection limit, wait for an available connection
        return $this->waitForAvailableConnection($connectionName);
    }

    /**
     * Release a connection back to the pool
     *
     * @param AdapterInterface $connection
     * @return void
     */
    public function releaseConnection(AdapterInterface $connection): void
    {
        foreach ($this->connections as $key => $poolConnection) {
            if ($poolConnection['connection'] === $connection) {
                unset($this->activeConnections[$key]);
                break;
            }
        }
    }

    /**
     * Create a new connection
     *
     * @param string $connectionName
     * @return AdapterInterface
     */
    private function createNewConnection(string $connectionName): AdapterInterface
    {
        $connection = $this->resourceConnection->getConnection($connectionName);

        // Apply optimized configuration
        if ($connection instanceof Mysql) {
            $this->optimizeConnection($connection);
        }

        $key = uniqid('conn_', true);
        $this->connections[$key] = [
            'name' => $connectionName,
            'connection' => $connection,
            'created' => time()
        ];
        $this->activeConnections[$key] = time();

        return $connection;
    }

    /**
     * Wait for an available connection
     *
     * @param string $connectionName
     * @return AdapterInterface
     */
    private function waitForAvailableConnection(string $connectionName): never
    {
        $maxWaitTime = 30; // Maximum wait time in seconds
        $startTime = time();

        while (time() - $startTime < $maxWaitTime) {
            $this->cleanupIdleConnections();

            foreach ($this->connections as $key => $connection) {
                if (!isset($this->activeConnections[$key]) && $connection['name'] === $connectionName) {
                    $this->activeConnections[$key] = time();
                    return $connection['connection'];
                }
            }

            // Wait a bit before checking again
            usleep(100000); // 100ms
        }

        $this->logger?->error('Connection pool exhausted, no connections available');
        throw new \RuntimeException('Connection pool exhausted, no connections available');
    }

    /**
     * Clean up idle connections
     *
     * @return void
     */
    private function cleanupIdleConnections(): void
    {
        $now = time();

        foreach ($this->connections as $key => $connection) {
            // Keep minimum number of connections
            if (count($this->connections) <= $this->minConnections) {
                break;
            }

            // Remove idle connections
            if (!isset($this->activeConnections[$key]) &&
                ($now - $connection['created'] > $this->idleTimeout)) {
                try {
                    $connection['connection']->closeConnection();
                } catch (\Exception $e) {
                    $this->logger?->error('Error closing database connection: ' . $e->getMessage());
                }
                unset($this->connections[$key]);
            }
        }
    }

    /**
     * Optimize MySQL connection settings
     *
     * @param Mysql $connection
     * @return void
     */
    private function optimizeConnection(Mysql $connection): void
    {
        try {
            // Get configuration from env.php
            $config = $this->deploymentConfig->get('db/connection/default');

            // Default optimization settings
            $optimizations = [
                'SET SESSION sql_mode = ?'                => 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION',
                'SET SESSION wait_timeout = ?'            => 28800,  // 8 hours
                'SET SESSION interactive_timeout = ?'     => 28800,  // 8 hours
                'SET SESSION net_read_timeout = ?'        => 30,
                'SET SESSION net_write_timeout = ?'       => 60,
                'SET SESSION max_allowed_packet = ?'      => 16777216, // 16M
                'SET SESSION innodb_lock_wait_timeout = ?' => 50,
                'SET SESSION group_concat_max_len = ?'    => 32768,  // 32K
            ];

            // Override with custom settings if defined in env.php
            if (isset($config['variables'])) {
                $optimizations = array_merge($optimizations, $config['variables']);
            }

            // Apply optimizations
            foreach ($optimizations as $query => $value) {
                $connection->query($query, [$value]);
            }

            // Set transaction isolation level
            $connection->query(
                'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'
            );

        } catch (\Exception $e) {
            $this->logger?->error('Error optimizing database connection: ' . $e->getMessage());
        }
    }
}
