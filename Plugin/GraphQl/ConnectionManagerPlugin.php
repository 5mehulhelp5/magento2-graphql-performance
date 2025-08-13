<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\App\RequestInterface;
use Magento\GraphQl\Model\Query\Context;
use Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionManager;
use Magento\Framework\GraphQl\Schema;

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
     * @param Schema $schema GraphQL schema
     * @param string|null $source GraphQL query source
     * @param Context|null $context Query context
     * @param array|null $variables Query variables
     * @param string|null $operationName Operation name
     * @param array|null $extensions GraphQL extensions
     * @return array
     */
    public function aroundProcess(
        QueryProcessor $subject,
        \Closure $proceed,
        Schema $schema,
        ?string $source = null,
        ?Context $context = null,
        ?array $variables = null,
        ?string $operationName = null,
        ?array $extensions = null
    ): array {
        // Parse the query to determine operation type
        try {
            if (empty($source)) {
                return $proceed($schema, $source, $context, $variables, $operationName, $extensions);
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
            return $proceed($schema, $source, $context, $variables, $operationName, $extensions);
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
            $result = $proceed($schema, $source, $context, $variables, $operationName, $extensions);

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
