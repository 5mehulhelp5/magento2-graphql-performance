<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver;

use Sterk\GraphQlPerformance\Api\OptimizedFieldInterface;

trait OptimizedFieldTrait
{
    /**
     * Get cache hash
     *
     * @return string
     */
    public function getCacheHash(): string
    {
        return hash('sha256', $this->getEntityType() . '_' . $this->getBatchKey());
    }

    /**
     * Get cache TTL
     *
     * @return int
     */
    public function getCacheTtl(): int
    {
        return 3600; // 1 hour by default
    }

    /**
     * Get cache tags
     *
     * @return array<string>
     */
    public function getCacheTags(): array
    {
        return [$this->getEntityType()];
    }

    /**
     * Get batch key
     *
     * @return string
     */
    public function getBatchKey(): string
    {
        return $this->getEntityType() . '_batch';
    }

    /**
     * Get batch size
     *
     * @return int
     */
    public function getBatchSize(): int
    {
        return 100; // Default batch size
    }

    /**
     * Get entity type
     *
     * @return string
     */
    abstract protected function getEntityType(): string;
}
