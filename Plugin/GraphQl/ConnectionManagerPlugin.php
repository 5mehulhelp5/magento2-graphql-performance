<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\App\RequestInterface;
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
     * @param RequestInterface $request Request object
     * @param string|null $query GraphQL query
     * @param string|null $operationName Operation name
     * @param array|null $variables Query variables
     * @param array|null $extensions GraphQL extensions
     * @return array
     */
    public function aroundProcess(
        QueryProcessor $subject,
        \Closure $proceed,
        RequestInterface $request,
        ?string $query = null,
        ?string $operationName = null,
        ?array $variables = null,
        ?array $extensions = null
    ): array {
        // Parse the query to determine operation type
        try {
            if ($query === null) {
                return $proceed($request, $query, $operationName, $variables, $extensions);
            }

            $documentNode = \GraphQL\Language\Parser::parse(new \GraphQL\Language\Source($query));
            $isMutation = false;

            foreach ($documentNode->definitions as $definition) {
                if ($definition->kind === 'OperationDefinition' && $definition->operation === 'mutation') {
                    $isMutation = true;
                    break;
                }
            }
        } catch (\Exception $e) {
            // If parsing fails, proceed with original request
            return $proceed($request, $query, $operationName, $variables, $extensions);
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
            $result = $proceed($request, $query, $operationName, $variables, $extensions);

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
