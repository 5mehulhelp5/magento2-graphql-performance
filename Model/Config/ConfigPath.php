<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Config;

/**
 * Enum defining configuration paths for GraphQL performance settings
 *
 * This enum contains the base paths for different configuration sections
 * in the Magento system configuration. Each case represents a specific
 * section of GraphQL performance-related settings.
 */
enum ConfigPath: string
{
    case CACHE = 'graphql_performance/cache/';
    case QUERY = 'graphql_performance/query/';
    case CONNECTION_POOL = 'graphql_performance/connection_pool/';
    case MONITORING = 'graphql_performance/monitoring/';
    case FIELD_RESOLVERS = 'graphql_performance/field_resolvers/';

    /**
     * Get full path for a field
     *
     * @param  string $field
     * @return string
     */
    public function getPath(string $field = ''): string
    {
        return $this->value . $field;
    }
}
