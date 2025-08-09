<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

abstract class CachedResolver implements ResolverInterface
{
    public function __construct(
        private readonly ResolverCache $cache
    ) {}

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
        $cacheKey = $this->generateCacheKey($field, $context, $info, $value, $args);

        // Try to get from cache
        $cachedData = $this->cache->get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        // Execute resolver and cache result
        $result = $this->resolveData($field, $context, $info, $value, $args);

        // Cache the result
        $this->cache->set(
            $cacheKey,
            $result,
            $this->getCacheTags($field, $context, $info, $value, $args)
        );

        return $result;
    }

    /**
     * Generate cache key for the resolver
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
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
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
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

    /**
     * Actual resolver implementation
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return mixed
     */
    abstract protected function resolveData(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    );
}
