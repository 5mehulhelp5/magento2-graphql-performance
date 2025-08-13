<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\App\RequestInterface;
use Magento\GraphQl\Model\Query\Context;
use Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionManager;

/**
 * Plugin for managing database connections in GraphQL operations
 *
 * This plugin handles database connection management for GraphQL queries and mutations,
 * ensuring proper connection pooling, transaction management, and resource cleanup.
 * It automatically uses read connections for queries and write connections for mutations.
 */
class ConnectionManagerPlugin
{
    /**
     * @param ConnectionManager $connectionManager Service for managing database connections
     */
    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {
    }

    /**
     * Manage database connections for GraphQL queries
     *
     * @param QueryProcessor $subject Query processor instance
     * @param \Closure $proceed Original method
     * @param mixed ...$args All method arguments
     * @return array
     */
    public function aroundProcess(
        QueryProcessor $subject,
        \Closure $proceed,
        ...$args
    ): array {
        // Extract arguments
        $schema = $args[0] ?? null;
        $source = $args[1] ?? null;
        $context = $args[2] ?? null;
        $variables = $args[3] ?? null;
        $operationName = $args[4] ?? null;
        $extensions = $args[5] ?? null;

        // Parse the query to determine operation type
        try {
            if (empty($source)) {
                return $proceed(...$args);
            }

            $documentNode = \GraphQL\Language\Parser::parse(new \GraphQL\Language\Source($source));
            $isMutation = false;

            foreach ($documentNode->definitions as $definition) {
                if ($definition->kind === 'OperationDefinition' && $definition->operation === 'mutation') {
                    $isMutation = true;
                    break;
                }
            }
        } catch (\Exception $e) {
            // If parsing fails, proceed with original request
            return $proceed(...$args);
        }

        try {
            if ($isMutation) {
                // Use write connection for mutations
                $connection = $this->connectionManager->getConnection(forWrite: true);
            } else {
                // Use read connection for queries
                $connection = $this->connectionManager->getConnection();
            }

            // Execute the query
            $result = $proceed(...$args);

            // Handle transaction for mutations
            if ($isMutation) {
                $this->connectionManager->commitAndRelease();
            } else {
                $this->connectionManager->releaseReadConnections();
            }

            return $result;
        } catch (\Exception $e) {
            // Rollback transaction on error for mutations
            if ($isMutation) {
                $this->connectionManager->rollbackAndRelease();
            } else {
                $this->connectionManager->releaseReadConnections();
            }
            throw $e;
        }
    }
}