<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Security;

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
        return match($this) {
            self::INTROSPECTION => 'Introspection queries are not allowed',
            self::DANGEROUS_FIELDS => 'Query contains sensitive field names',
            self::SYSTEM_QUERIES => 'System-level queries are not allowed'
        };
    }

    /**
     * Get all patterns as array
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
