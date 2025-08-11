<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\DataLoader\BatchDataLoader;

abstract class BatchedResolver implements ResolverInterface
{
    public function __construct(
        protected readonly ResolverCache $cache,
        protected readonly BatchDataLoader $dataLoader
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $ids = $this->getIdsToLoad($field, $context, $info, $value, $args);

        if (empty($ids)) {
            return $this->getEmptyResult();
        }

        // Try to get from cache first
        $cacheKey = $this->generateCacheKey($field, $context, $info, $value, $args);
        $cachedData = $this->cache->get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        // Load data in batch
        $items = $this->dataLoader->loadMany($ids);

        // Transform loaded items
        $result = $this->transformLoadedItems($items, $field, $context, $info, $value, $args);

        // Cache the result
        $this->cache->set(
            $cacheKey,
            $result,
            $this->getCacheTags($field, $context, $info, $value, $args)
        );

        return $result;
    }

    /**
     * Get IDs that need to be loaded
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array|null  $value
     * @param  array|null  $args
     * @return array
     */
    abstract protected function getIdsToLoad(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value,
        ?array $args
    ): array;

    /**
     * Transform loaded items into the final result
     *
     * @param  array       $items
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array|null  $value
     * @param  array|null  $args
     * @return mixed
     */
    abstract protected function transformLoadedItems(
        array $items,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value,
        ?array $args
    );

    /**
     * Get empty result when no IDs are found
     *
     * @return mixed
     */
    abstract protected function getEmptyResult();

    /**
     * Generate cache key for the resolver
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array|null  $value
     * @param  array|null  $args
     * @return string
     */
    protected function generateCacheKey(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value,
        ?array $args
    ): string {
        $keyParts = [
            static::class,
            $field->getName(),
            $info->operation->name->value,
            json_encode($args),
            json_encode($value)
        ];

        if (method_exists($context, 'getExtensionAttributes')) {
            $store = $context->getExtensionAttributes()->getStore();
            if ($store) {
                $keyParts[] = $store->getId();
            }
        }

        return implode(':', array_filter($keyParts));
    }

    /**
     * Get cache tags for the resolver
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array|null  $value
     * @param  array|null  $args
     * @return array
     */
    protected function getCacheTags(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value,
        ?array $args
    ): array {
        return [];
    }
}
