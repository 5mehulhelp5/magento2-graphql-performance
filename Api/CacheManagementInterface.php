<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Api;

interface CacheManagementInterface
{
    /**
     * Clean GraphQL cache
     *
     * @return bool
     * @api
     */
    public function clean(): bool;

    /**
     * Warm GraphQL cache
     *
     * @return bool
     * @api
     */
    public function warm(): bool;
}
