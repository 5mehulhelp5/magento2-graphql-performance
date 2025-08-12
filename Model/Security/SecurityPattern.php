<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Security;

/**
 * Enum defining security patterns for GraphQL query validation
 *
 * This enum contains regular expression patterns used to identify potentially
 * dangerous or restricted GraphQL queries, including introspection queries,
 * sensitive field access, and system-level operations.
 */
enum SecurityPattern: string
{
    case INTROSPECTION = '__schema|__type';
    case DANGEROUS_FIELDS = 'password|token|secret|key';
    case SYSTEM_QUERIES = 'system\.|\$where|eval\(';

    /**
     * Get pattern description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return match (self::from($this)) {
            self::INTROSPECTION => 'Introspection queries are not allowed',
            self::DANGEROUS_FIELDS => 'Query contains sensitive field names',
            self::SYSTEM_QUERIES => 'System-level queries are not allowed'
        };
    }

    /**
     * Get all patterns as array
     *
     * Note: This method must be static as it provides a utility function
     * for accessing all enum cases. It's a common pattern in enums to
     * provide static methods for working with the enum values as a group.
     *
     * @return array<string, string>
     */
    public static function getPatterns(): array
    {
        $patterns = [];
        foreach (self::cases() as $case) {
            $patterns[$case->name] = $case->value;
        }
        return $patterns;
    }
}
