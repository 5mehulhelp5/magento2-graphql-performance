<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Cache;

/**
 * Enum for cache tag strategy options
 *
 * This enum defines the available strategies for cache tag generation:
 * - SPECIFIC: Generates detailed tags for individual entities
 * - GROUPED: Groups tags by entity type
 * - MINIMAL: Uses minimal tagging for better performance
 */
enum CacheTagStrategy: string
{
    case SPECIFIC = 'specific';
    case GROUPED = 'grouped';
    case MINIMAL = 'minimal';

    /**
     * Get default strategy
     *
     * Note: This method must be static as it's part of an enum class.
     * Enums are value objects and cannot have instance methods.
     *
     * @return self
     */
    public static function getDefault(): self
    {
        return self::SPECIFIC;
    }

    /**
     * Check if strategy is valid
     *
     * Note: This method must be static as it's part of an enum class.
     * Enums are value objects and cannot have instance methods.
     *
     * @param  string $strategy
     * @return bool
     */
    public static function isValid(string $strategy): bool
    {
        return in_array($strategy, array_column(self::cases(), 'value'));
    }
}
