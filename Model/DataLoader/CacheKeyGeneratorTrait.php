<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

trait CacheKeyGeneratorTrait
{
    /**
     * Generate cache key for an ID
     *
     * @param string $id
     * @return string
     */
    protected function generateCacheKey(string $id): string
    {
        return sprintf('%s_data_%s', $this->getEntityType(), $id);
    }

    /**
     * Get cache tags for an item
     *
     * @param mixed $item
     * @return array
     */
    protected function getCacheTags(mixed $item): array
    {
        $tags = [$this->getEntityType()];
        if ($item && method_exists($item, 'getId')) {
            $tags[] = sprintf('%s_%s', $this->getEntityType(), $item->getId());
        }
        return $tags;
    }

    /**
     * Get entity type
     *
     * @return string
     */
    abstract protected function getEntityType(): string;
}
