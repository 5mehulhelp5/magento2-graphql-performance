<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\PromiseAdapter;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

class FrequentCategoryDataLoader extends FrequentDataLoader
{
    private const BATCH_SIZE = 50;

    public function __construct(
        PromiseAdapter $promiseAdapter,
        ResolverCache $cache,
        ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        int $cacheLifetime = 7200 // 2 hours for categories
    ) {
        parent::__construct($promiseAdapter, $cache, $resourceConnection, $cacheLifetime);
    }

    protected function loadFromDatabase(array $ids): array
    {
        $connection = $this->resourceConnection->getConnection();
        $storeId = $this->storeManager->getStore()->getId();

        // Split IDs into batches
        $batches = array_chunk($ids, self::BATCH_SIZE);
        $result = [];

        foreach ($batches as $batchIds) {
            // Load base category data
            $select = $connection->select()
                ->from(
                    ['e' => $this->resourceConnection->getTableName('catalog_category_entity')],
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
                $result[$categoryId] = array_merge(
                    $item,
                    $attributeValues[$categoryId] ?? [],
                    ['product_count' => $productCounts[$categoryId] ?? 0],
                    ['url_rewrite' => $urlRewrites[$categoryId] ?? null]
                );
            }
        }

        return $result;
    }

    private function loadAttributes(array $categoryIds, int $storeId): array
    {
        $connection = $this->resourceConnection->getConnection();
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
                    ['t' => $this->resourceConnection->getTableName('catalog_category_entity_varchar')],
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

    private function loadProductCounts(array $categoryIds): array
    {
        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                ['cat_index' => $this->resourceConnection->getTableName('catalog_category_product')],
                [
                    'category_id',
                    'product_count' => new \Zend_Db_Expr('COUNT(DISTINCT product_id)')
                ]
            )
            ->where('category_id IN (?)', $categoryIds)
            ->group('category_id');

        return $connection->fetchPairs($select);
    }

    private function loadUrlRewrites(array $categoryIds, int $storeId): array
    {
        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('url_rewrite'),
                ['entity_id', 'request_path']
            )
            ->where('entity_type = ?', 'category')
            ->where('entity_id IN (?)', $categoryIds)
            ->where('store_id = ?', $storeId)
            ->where('metadata IS NULL'); // Get only direct rewrites

        return $connection->fetchPairs($select);
    }

    private function getAttributeId(string $attributeCode): ?int
    {
        static $attributeIds = [];

        if (!isset($attributeIds[$attributeCode])) {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('eav_attribute'),
                    'attribute_id'
                )
                ->where('attribute_code = ?', $attributeCode)
                ->where('entity_type_id = ?', 3); // 3 is catalog_category

            $attributeIds[$attributeCode] = $connection->fetchOne($select);
        }

        return $attributeIds[$attributeCode] ?: null;
    }

    protected function generateCacheKey(string $id): string
    {
        $storeId = $this->storeManager->getStore()->getId();
        return sprintf('frequent_category_%s_store_%d', $id, $storeId);
    }

    protected function getCacheTags(mixed $item): array
    {
        $tags = ['catalog_category'];
        if (isset($item['entity_id'])) {
            $tags[] = 'catalog_category_' . $item['entity_id'];
        }
        return $tags;
    }
}
