<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Cache;

class CacheKeyGenerator
{
    /**
     * Generate cache key for query
     *
     * @param string $query
     * @param array|null $variables
     * @param array $additionalData
     * @return string
     */
    public function generate(string $query, ?array $variables = null, array $additionalData = []): string
    {
        $key = array_merge([
            'query' => $query,
            'variables' => $variables
        ], $additionalData);

        return hash('sha256', json_encode($key));
    }
}
