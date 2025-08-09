<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema;
use Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionManager;

class ConnectionManagerPlugin
{
    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {}

    /**
     * Manage database connections for GraphQL queries
     *
     * @param QueryProcessor $subject
     * @param callable $proceed
     * @param Schema $schema
     * @param string $source
     * @param ContextInterface|null $contextValue
     * @param array|null $variableValues
     * @param string|null $operationName
     * @return array
     */
    public function aroundExecute(
        QueryProcessor $subject,
        callable $proceed,
        Schema $schema,
        string $source,
        ?ContextInterface $contextValue = null,
        ?array $variableValues = null,
        ?string $operationName = null
    ): array {
        // Parse the query to determine operation type
        $documentNode = \GraphQL\Language\Parser::parse(new \GraphQL\Language\Source($source));
        $isMutation = false;

        foreach ($documentNode->definitions as $definition) {
            if ($definition->kind === 'OperationDefinition' && $definition->operation === 'mutation') {
                $isMutation = true;
                break;
            }
        }

        try {
            if ($isMutation) {
                // Use write connection for mutations
                $connection = $this->connectionManager->getWriteConnection();
            } else {
                // Use read connection for queries
                $connection = $this->connectionManager->getReadConnection();
            }

            // Execute the query
            $result = $proceed($schema, $source, $contextValue, $variableValues, $operationName);

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
