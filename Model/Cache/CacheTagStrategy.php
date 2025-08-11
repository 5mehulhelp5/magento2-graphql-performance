<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Cache;

enum CacheTagStrategy: string
{
    case SPECIFIC = 'specific';
    case GROUPED = 'grouped';
    case MINIMAL = 'minimal';

    /**
     * Get default strategy
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
     * @param  string $strategy
     * @return bool
     */
    public static function isValid(string $strategy): bool
    {
        return in_array($strategy, array_column(self::cases(), 'value'));
    }
}
