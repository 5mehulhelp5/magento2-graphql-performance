<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

class FieldResolverManager
{
    private array $resolvers = [];
    private array $batchedFields = [];
    private array $pendingResolvers = [];

    public function __construct(
        private readonly ResolverCache $cache
    ) {
    }

    /**
     * Register a field resolver
     *
     * @param  string   $type
     * @param  string   $field
     * @param  callable $resolver
     * @param  bool     $batchable
     * @return void
     */
    public function registerResolver(string $type, string $field, callable $resolver, bool $batchable = false): void
    {
        $this->resolvers[$type][$field] = $resolver;
        if ($batchable) {
            $this->batchedFields[$type][$field] = true;
        }
    }

    /**
     * Resolve a field
     *
     * @param  string      $type
     * @param  string      $field
     * @param  mixed       $source
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array|null  $value
     * @param  array|null  $args
     * @return mixed
     */
    public function resolveField(
        string $type,
        string $field,
        $source,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        // Check if field is batchable
        if (isset($this->batchedFields[$type][$field])) {
            return $this->handleBatchableField($type, $field, $source, $context, $info, $value, $args);
        }

        // Try to get from cache
        $cacheKey = $this->generateCacheKey($type, $field, $source, $context, $info, $value, $args);
        $cachedData = $this->cache->get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        // Resolve field
        $result = $this->executeResolver($type, $field, $source, $context, $info, $value, $args);

        // Cache result
        $this->cache->set(
            $cacheKey,
            $result,
            $this->getCacheTags($type, $field, $source),
            3600
        );

        return $result;
    }

    /**
     * Handle batchable field
     *
     * @param  string      $type
     * @param  string      $field
     * @param  mixed       $source
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array|null  $value
     * @param  array|null  $args
     * @return mixed
     */
    private function handleBatchableField(
        string $type,
        string $field,
        $source,
        $context,
        ResolveInfo $info,
        ?array $value,
        ?array $args
    ) {
        $key = $this->getBatchKey($type, $field, $source);

        // Queue resolver for batch execution
        if (!isset($this->pendingResolvers[$key])) {
            $this->pendingResolvers[$key] = [
                'type' => $type,
                'field' => $field,
                'sources' => [],
                'context' => $context,
                'info' => $info,
                'value' => $value,
                'args' => $args
            ];
        }

        $this->pendingResolvers[$key]['sources'][] = $source;

        // Return a promise that will be resolved when batch is executed
        return new \GraphQL\Deferred(
            function () use ($key, $source) {
                if (!isset($this->pendingResolvers[$key])) {
                    return null;
                }

                $data = $this->executeBatchResolver($key);
                return $data[$source->getId()] ?? null;
            }
        );
    }

    /**
     * Execute batch resolver
     *
     * @param  string $key
     * @return array
     */
    private function executeBatchResolver(string $key): array
    {
        $pending = $this->pendingResolvers[$key];
        unset($this->pendingResolvers[$key]);

        // Try to get from cache
        $cacheKey = $this->generateBatchCacheKey($pending);
        $cachedData = $this->cache->get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        // Execute resolver
        $result = $this->executeResolver(
            $pending['type'],
            $pending['field'],
            $pending['sources'],
            $pending['context'],
            $pending['info'],
            $pending['value'],
            $pending['args']
        );

        // Cache result
        $this->cache->set(
            $cacheKey,
            $result,
            $this->getBatchCacheTags($pending),
            3600
        );

        return $result;
    }

    /**
     * Execute resolver
     *
     * @param  string      $type
     * @param  string      $field
     * @param  mixed       $source
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array|null  $value
     * @param  array|null  $args
     * @return mixed
     */
    private function executeResolver(
        string $type,
        string $field,
        $source,
        $context,
        ResolveInfo $info,
        ?array $value,
        ?array $args
    ) {
        if (!isset($this->resolvers[$type][$field])) {
            return null;
        }

        return ($this->resolvers[$type][$field])($source, $context, $info, $value, $args);
    }

    /**
     * Generate cache key for field
     *
     * @param  string      $type
     * @param  string      $field
     * @param  mixed       $source
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array|null  $value
     * @param  array|null  $args
     * @return string
     */
    private function generateCacheKey(
        string $type,
        string $field,
        $source,
        $context,
        ResolveInfo $info,
        ?array $value,
        ?array $args
    ): string {
        $keyParts = [
            'field_resolver',
            $type,
            $field,
            $source->getId() ?? '',
            json_encode($value),
            json_encode($args)
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
     * Generate cache key for batch
     *
     * @param  array $pending
     * @return string
     */
    private function generateBatchCacheKey(array $pending): string
    {
        $sourceIds = array_map(
            function ($source) {
                return $source->getId();
            },
            $pending['sources']
        );

        sort($sourceIds);

        $keyParts = [
            'batch_resolver',
            $pending['type'],
            $pending['field'],
            implode(',', $sourceIds),
            json_encode($pending['value']),
            json_encode($pending['args'])
        ];

        if (method_exists($pending['context'], 'getExtensionAttributes')) {
            $store = $pending['context']->getExtensionAttributes()->getStore();
            if ($store) {
                $keyParts[] = $store->getId();
            }
        }

        return implode(':', array_filter($keyParts));
    }

    /**
     * Get cache tags for field
     *
     * @param  string $type
     * @param  string $field
     * @param  mixed  $source
     * @return array
     */
    private function getCacheTags(string $type, string $field, $source): array
    {
        $tags = ['graphql', "graphql_field_{$type}_{$field}"];

        if (method_exists($source, 'getId')) {
            $tags[] = "graphql_entity_{$type}_{$source->getId()}";
        }

        return $tags;
    }

    /**
     * Get cache tags for batch
     *
     * @param  array $pending
     * @return array
     */
    private function getBatchCacheTags(array $pending): array
    {
        $tags = ['graphql', "graphql_field_{$pending['type']}_{$pending['field']}"];

        foreach ($pending['sources'] as $source) {
            if (method_exists($source, 'getId')) {
                $tags[] = "graphql_entity_{$pending['type']}_{$source->getId()}";
            }
        }

        return $tags;
    }

    /**
     * Get batch key
     *
     * @param  string $type
     * @param  string $field
     * @param  mixed  $source
     * @return string
     */
    private function getBatchKey(string $type, string $field, $source): string
    {
        return "{$type}:{$field}";
    }
}
