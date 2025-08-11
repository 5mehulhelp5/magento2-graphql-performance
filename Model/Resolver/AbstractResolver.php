<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\BatchServiceContractResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Api\OptimizedFieldInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;
use Sterk\GraphQlPerformance\Model\Resolver\OptimizedFieldTrait;

abstract class AbstractResolver implements BatchServiceContractResolverInterface, OptimizedFieldInterface
{
    use OptimizedFieldTrait;
    public function __construct(
        protected readonly ResolverCache $cache,
        protected readonly QueryTimer $queryTimer
    ) {
    }

    /**
     * Resolve GraphQL field
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array       $value
     * @param  array       $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = [],
        array $args = []
    ): array {
        $this->queryTimer->start($info->operation->name->value, $info->operation->loc->source->body);

        try {
            // Try to get from cache first
            $cacheKey = $this->generateCacheKey($field, $context, $info, $value, $args);
            $cachedData = $this->cache->get($cacheKey);

            if ($cachedData !== null) {
                $this->queryTimer->stop($info->operation->name->value, $info->operation->loc->source->body, true);
                return $cachedData;
            }

            // Get data
            $result = $this->resolveData($field, $context, $info, $value, $args);

            // Cache the result
            $this->cache->set(
                $cacheKey,
                $result,
                $this->getCacheTags(),
                $this->getCacheLifetime()
            );

            $this->queryTimer->stop($info->operation->name->value, $info->operation->loc->source->body);

            return $result;
        } catch (\Exception $e) {
            $this->queryTimer->stop($info->operation->name->value, $info->operation->loc->source->body);
            throw $e;
        }
    }

    /**
     * Generate cache key
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array       $value
     * @param  array       $args
     * @return string
     */
    protected function generateCacheKey(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value,
        array $args
    ): string {
        $keyParts = [
            $this->getEntityType() . '_query',
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
     * Get cache lifetime in seconds
     *
     * @return int
     */
    protected function getCacheLifetime(): int
    {
        return 3600; // 1 hour by default
    }

    /**
     * Get entity type for cache key generation
     *
     * @return string
     */
    abstract protected function getEntityType(): string;

    /**
     * Get cache tags
     *
     * @return array
     */
    abstract protected function getCacheTags(): array;

    /**
     * Resolve data
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array       $value
     * @param  array       $args
     * @return array
     */
    abstract protected function resolveData(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = [],
        array $args = []
    ): array;
}
