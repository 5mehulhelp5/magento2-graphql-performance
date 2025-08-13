<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Security;

/**
 * Enum defining security patterns for GraphQL query validation
 *
 * This enum contains regular expression patterns used to identify potentially
 * dangerous or restricted GraphQL queries, including sensitive field access
 * and system-level operations.
 */
enum SecurityPattern: string
{
    case DANGEROUS_FIELDS = 'password|token|secret|key';
    case SYSTEM_QUERIES = 'system\\.|\\$where|eval\\(';

    /**
     * Get pattern description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::DANGEROUS_FIELDS => 'Query contains sensitive fields that are not allowed',
            self::SYSTEM_QUERIES => 'System-level queries are not allowed',
        };
    }
}
