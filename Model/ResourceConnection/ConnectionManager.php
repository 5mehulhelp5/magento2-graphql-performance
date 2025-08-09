<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\ResourceConnection;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\LoggerInterface;
use Magento\Framework\App\DeploymentConfig;

class ConnectionManager
{
    private array $transactionConnections = [];
    private array $readConnections = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ResourceConnection $resourceConnection,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Get connection for read operations
     *
     * @param string $connectionName
     * @return AdapterInterface
     */
    public function getReadConnection(string $connectionName = ResourceConnection::DEFAULT_CONNECTION): AdapterInterface
    {
        $requestId = $this->getRequestId();

        if (!isset($this->readConnections[$requestId][$connectionName])) {
            $connection = $this->connectionPool->getConnection($connectionName);
            $this->readConnections[$requestId][$connectionName] = $connection;
        }

        return $this->readConnections[$requestId][$connectionName];
    }

    /**
     * Get connection for write operations
     *
     * @param string $connectionName
     * @return AdapterInterface
     */
    public function getWriteConnection(string $connectionName = ResourceConnection::DEFAULT_CONNECTION): AdapterInterface
    {
        $requestId = $this->getRequestId();

        if (!isset($this->transactionConnections[$requestId][$connectionName])) {
            $connection = $this->connectionPool->getConnection($connectionName);
            $this->transactionConnections[$requestId][$connectionName] = $connection;

            // Start transaction
            $connection->beginTransaction();
        }

        return $this->transactionConnections[$requestId][$connectionName];
    }

    /**
     * Commit transaction and release connection
     *
     * @param string $connectionName
     * @return void
     */
    public function commitAndRelease(string $connectionName = ResourceConnection::DEFAULT_CONNECTION): void
    {
        $requestId = $this->getRequestId();

        if (isset($this->transactionConnections[$requestId][$connectionName])) {
            try {
                $connection = $this->transactionConnections[$requestId][$connectionName];
                $connection->commit();
                $this->connectionPool->releaseConnection($connection);
                unset($this->transactionConnections[$requestId][$connectionName]);
            } catch (\Exception $e) {
                $this->logger?->error('Error committing transaction: ' . $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Rollback transaction and release connection
     *
     * @param string $connectionName
     * @return void
     */
    public function rollbackAndRelease(string $connectionName = ResourceConnection::DEFAULT_CONNECTION): void
    {
        $requestId = $this->getRequestId();

        if (isset($this->transactionConnections[$requestId][$connectionName])) {
            try {
                $connection = $this->transactionConnections[$requestId][$connectionName];
                $connection->rollBack();
                $this->connectionPool->releaseConnection($connection);
                unset($this->transactionConnections[$requestId][$connectionName]);
            } catch (\Exception $e) {
                $this->logger?->error('Error rolling back transaction: ' . $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Release read connections
     *
     * @param string|null $connectionName
     * @return void
     */
    public function releaseReadConnections(?string $connectionName = null): void
    {
        $requestId = $this->getRequestId();

        if (isset($this->readConnections[$requestId])) {
            if ($connectionName !== null) {
                if (isset($this->readConnections[$requestId][$connectionName])) {
                    $this->connectionPool->releaseConnection($this->readConnections[$requestId][$connectionName]);
                    unset($this->readConnections[$requestId][$connectionName]);
                }
            } else {
                foreach ($this->readConnections[$requestId] as $connection) {
                    $this->connectionPool->releaseConnection($connection);
                }
                unset($this->readConnections[$requestId]);
            }
        }
    }

    /**
     * Get unique request ID
     *
     * @return string
     */
    private function getRequestId(): string
    {
        return spl_object_hash($this);
    }

    /**
     * Clean up connections on destruct
     */
    public function __destruct()
    {
        $requestId = $this->getRequestId();

        // Release read connections
        if (isset($this->readConnections[$requestId])) {
            foreach ($this->readConnections[$requestId] as $connection) {
                $this->connectionPool->releaseConnection($connection);
            }
        }

        // Rollback and release transaction connections
        if (isset($this->transactionConnections[$requestId])) {
            foreach ($this->transactionConnections[$requestId] as $connectionName => $connection) {
                $this->rollbackAndRelease($connectionName);
            }
        }
    }
}
