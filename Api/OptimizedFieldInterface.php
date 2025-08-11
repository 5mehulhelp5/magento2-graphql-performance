<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Api;

interface OptimizedFieldInterface
{
    /**
     * Get cache hash
     *
     * @return string
     */
    public function getCacheHash(): string;

    /**
     * Get cache TTL
     *
     * @return int
     */
    public function getCacheTtl(): int;

    /**
     * Get cache tags
     *
     * @return array<string>
     */
    public function getCacheTags(): array;

    /**
     * Get batch key
     *
     * @return string
     */
    public function getBatchKey(): string;

    /**
     * Get batch size
     *
     * @return int
     */
    public function getBatchSize(): int;
}
