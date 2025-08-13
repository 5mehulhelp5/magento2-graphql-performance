<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Product;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\DataLoader\BatchDataLoader;

/**
 * Optimized resolver for watch-specific attributes
 *
 * This resolver implements batch loading and caching specifically optimized
 * for watch attributes like mechanism, case size, etc. It uses a specialized
 * caching strategy that groups related attributes together.
 */
class WatchAttributeResolver implements ResolverInterface
{
    private const WATCH_ATTRIBUTES = [
        'es_saat_mekanizma',
        'es_kasa_capi',
        'es_kasa_cinsi',
        'es_kordon_tipi',
        'es_swiss_made',
        'manufacturer'
    ];

    private const ATTRIBUTE_GROUPS = [
        'mechanism' => ['es_saat_mekanizma'],
        'case' => ['es_kasa_capi', 'es_kasa_cinsi'],
        'strap' => ['es_kordon_tipi'],
        'brand' => ['manufacturer', 'es_swiss_made']
    ];

    public function __construct(
        private readonly ResolverCache $cache,
        private readonly BatchDataLoader $dataLoader
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $productId = $value['entity_id'] ?? null;
        if (!$productId) {
            return null;
        }

        $attributeCode = $field->getName();
        if (!in_array($attributeCode, self::WATCH_ATTRIBUTES)) {
            return null;
        }

        // Try to get from cache
        $cacheKey = $this->generateCacheKey($productId, $attributeCode);
        $cachedValue = $this->cache->get($cacheKey);
        if ($cachedValue !== null) {
            return $cachedValue;
        }

        // Get attribute group
        $group = $this->getAttributeGroup($attributeCode);

        // Load all attributes in the same group
        $groupAttributes = $this->loadAttributeGroup($productId, $group);

        // Cache all attributes in the group
        foreach ($groupAttributes as $code => $value) {
            $this->cache->set(
                $this->generateCacheKey($productId, $code),
                $value,
                ['product_' . $productId, 'attribute_' . $code],
                3600 // 1 hour cache
            );
        }

        return $groupAttributes[$attributeCode] ?? null;
    }

    private function generateCacheKey(int $productId, string $attributeCode): string
    {
        return sprintf('watch_attr_%d_%s', $productId, $attributeCode);
    }

    private function getAttributeGroup(string $attributeCode): string
    {
        foreach (self::ATTRIBUTE_GROUPS as $group => $attributes) {
            if (in_array($attributeCode, $attributes)) {
                return $group;
            }
        }
        return 'default';
    }

    private function loadAttributeGroup(int $productId, string $group): array
    {
        $attributes = self::ATTRIBUTE_GROUPS[$group] ?? [];
        if (empty($attributes)) {
            return [];
        }

        // Use batch loader to efficiently load all attributes
        $values = [];
        foreach ($attributes as $attributeCode) {
            $values[$attributeCode] = $this->dataLoader->load([
                'product_id' => $productId,
                'attribute_code' => $attributeCode
            ]);
        }

        return $values;
    }
}
