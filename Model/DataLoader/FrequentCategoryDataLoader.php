<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\PromiseAdapter;
use Magento\Framework\ObjectManagerInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Data loader for frequently accessed category data
 *
 * This class provides optimized loading of category data, including attributes,
 * product counts, and URL rewrites. It implements batch loading and caching
 * strategies to improve performance when loading data for multiple categories.
 */
class FrequentCategoryDataLoader extends FrequentDataLoader
{
    /**
     * @var int Maximum number of categories to load in a single batch
     */
    private const BATCH_SIZE = 50;

    /**
     * @param ObjectManagerInterface $objectManager Object manager for lazy loading
     * @param ResolverCache $cache Cache service
     * @param PromiseAdapter $promiseAdapter GraphQL promise adapter
     * @param StoreManagerInterface $storeManager Store manager service
     * @param int $cacheLifetime Cache lifetime in seconds
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ResolverCache $cache,
        PromiseAdapter $promiseAdapter,
        private readonly StoreManagerInterface $storeManager,
        int $cacheLifetime = 7200 // 2 hours for categories
    ) {
        parent::__construct($objectManager, $cache, $promiseAdapter, $cacheLifetime);
    }

    /**
     * Load category data from database
     *
     * This method loads category data in batches, including base data, attributes,
     * product counts, and URL rewrites. It optimizes database queries by using
     * batch loading and efficient joins.
     *
     * @param  array $ids Category IDs to load
     * @return array     Category data indexed by ID
     */
    protected function loadFromDatabase(array $ids): array
    {
        $resourceConnection = $this->objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resourceConnection->getConnection();
        $storeId = $this->storeManager->getStore()->getId();

        // Split IDs into batches
        $batches = array_chunk($ids, self::BATCH_SIZE);
        $result = [];

        foreach ($batches as $batchIds) {
            // Load base category data
            $select = $connection->select()
                ->from(
                    ['e' => $resourceConnection->getTableName('catalog_category_entity')],
                    [
                        'entity_id',
                        'parent_id',
                        'path',
                        'position',
                        'level',
                        'children_count',
                        'created_at',
                        'updated_at'
                    ]
                )
                ->where('e.entity_id IN (?)', $batchIds);

            $items = $connection->fetchAll($select);

            // Load category attributes
            $attributeValues = $this->loadAttributes($batchIds, $storeId);

            // Load product counts
            $productCounts = $this->loadProductCounts($batchIds);

            // Load URL rewrites
            $urlRewrites = $this->loadUrlRewrites($batchIds, $storeId);

            // Merge all data
            foreach ($items as $item) {
                $categoryId = $item['entity_id'];
                // Pre-allocate array with known keys to avoid array_merge
                $result[$categoryId] = $item;
                if (isset($attributeValues[$categoryId])) {
                    foreach ($attributeValues[$categoryId] as $key => $value) {
                        $result[$categoryId][$key] = $value;
                    }
                }
                $result[$categoryId]['product_count'] = $productCounts[$categoryId] ?? 0;
                $result[$categoryId]['url_rewrite'] = $urlRewrites[$categoryId] ?? null;
            }
        }

        return $result;
    }

    /**
     * Load category attributes from database
     *
     * This method loads frequently accessed category attributes like name, URL key,
     * description, etc. It handles store-specific values and fallbacks to default
     * values when needed.
     *
     * @param array $categoryIds Category IDs to load attributes for
     * @param int   $storeId    Store view ID
     * @return array           Attribute values indexed by category ID
     */
    private function loadAttributes(array $categoryIds, int $storeId): array {
        $resourceConnection = $this->objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resourceConnection->getConnection();
        $result = [];

        // Get frequently accessed attributes
        $attributes = ['name', 'url_key', 'description', 'is_active', 'include_in_menu', 'image'];

        foreach ($attributes as $attributeCode) {
            $attributeId = $this->getAttributeId($attributeCode);
            if (!$attributeId) {
                continue;
            }

            // Get attribute values
            $select = $connection->select()
                ->from(
                    ['t' => $resourceConnection->getTableName('catalog_category_entity_varchar')],
                    ['entity_id', 'value']
                )
                ->where('t.attribute_id = ?', $attributeId)
                ->where('t.entity_id IN (?)', $categoryIds)
                ->where('t.store_id IN (?)', [0, $storeId]);

            $values = $connection->fetchPairs($select);

            foreach ($values as $categoryId => $value) {
                if (!isset($result[$categoryId])) {
                    $result[$categoryId] = [];
                }
                $result[$categoryId][$attributeCode] = $value;
            }
        }

        return $result;
    }

    /**
     * Load product counts for categories
     *
     * This method counts the number of products associated with each category.
     * It uses an optimized query to get accurate product counts while avoiding
     * expensive joins.
     *
     * @param  array $categoryIds Category IDs to load product counts for
     * @return array             Product counts indexed by category ID
     */
    private function loadProductCounts(array $categoryIds): array
    {
        $resourceConnection = $this->objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                ['cat_index' => $resourceConnection->getTableName('catalog_category_product')],
                [
                    'category_id',
                    'product_count' => new \Zend_Db_Expr('COUNT(DISTINCT product_id)')
                ]
            )
            ->where('category_id IN (?)', $categoryIds)
            ->group('category_id');

        return $connection->fetchPairs($select);
    }

    /**
     * Load URL rewrites for categories
     *
     * This method loads the URL rewrites (URL paths) for categories. It only loads
     * direct rewrites, ignoring category-product and other complex rewrites.
     *
     * @param array $categoryIds Category IDs to load URL rewrites for
     * @param int   $storeId    Store view ID
     * @return array           URL rewrites indexed by category ID
     */
    private function loadUrlRewrites(array $categoryIds, int $storeId): array {
        $resourceConnection = $this->objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                $resourceConnection->getTableName('url_rewrite'),
                ['entity_id', 'request_path']
            )
            ->where('entity_type = ?', 'category')
            ->where('entity_id IN (?)', $categoryIds)
            ->where('store_id = ?', $storeId)
            ->where('metadata IS NULL'); // Get only direct rewrites

        return $connection->fetchPairs($select);
    }

    /**
     * Get attribute ID by code
     *
     * This method retrieves the attribute ID for a given attribute code. It uses
     * static caching to avoid repeated database lookups for the same attribute.
     *
     * @param  string   $attributeCode Attribute code to look up
     * @return int|null                Attribute ID or null if not found
     */
    private function getAttributeId(string $attributeCode): ?int
    {
        static $attributeIds = [];

        if (!isset($attributeIds[$attributeCode])) {
            $resourceConnection = $this->objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resourceConnection->getConnection();
            $select = $connection->select()
                ->from(
                    $resourceConnection->getTableName('eav_attribute'),
                    'attribute_id'
                )
                ->where('attribute_code = ?', $attributeCode)
                ->where('entity_type_id = ?', 3); // 3 is catalog_category

            $attributeIds[$attributeCode] = $connection->fetchOne($select);
        }

        return $attributeIds[$attributeCode] ?: null;
    }

    /**
     * Generate cache key for category data
     *
     * This method generates a unique cache key for category data that includes
     * both the category ID and store ID to ensure store-specific caching.
     *
     * @param  string $id Category ID
     * @return string    Cache key
     */
    protected function generateCacheKey(string $id): string
    {
        $storeId = $this->storeManager->getStore()->getId();
        return sprintf('frequent_category_%s_store_%d', $id, $storeId);
    }

    /**
     * Get cache tags for category data
     *
     * This method returns the cache tags used for cache invalidation. It includes
     * both a general category tag and a specific tag for the individual category.
     *
     * @param  mixed $item Category data
     * @return array      Cache tags
     */
    protected function getCacheTags(mixed $item): array
    {
        $tags = ['catalog_category'];
        if (isset($item['entity_id'])) {
            $tags[] = 'catalog_category_' . $item['entity_id'];
        }
        return $tags;
    }
}
