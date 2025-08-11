<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\PromiseAdapter;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

class FrequentProductDataLoader extends FrequentDataLoader
{
    private const BATCH_SIZE = 100;

    public function __construct(
        PromiseAdapter $promiseAdapter,
        ResolverCache $cache,
        ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        int $cacheLifetime = 3600
    ) {
        parent::__construct($promiseAdapter, $cache, $resourceConnection, $cacheLifetime);
    }

    protected function loadFromDatabase(array $ids): array
    {
        $connection = $this->resourceConnection->getConnection();
        $storeId = $this->storeManager->getStore()->getId();

        // Split IDs into batches to prevent large IN clauses
        $batches = array_chunk($ids, self::BATCH_SIZE);
        $result = [];

        foreach ($batches as $batchIds) {
            // Load base product data
            $select = $connection->select()
                ->from(
                    ['e' => $this->resourceConnection->getTableName('catalog_product_entity')],
                    ['entity_id', 'sku', 'type_id', 'created_at', 'updated_at']
                )
                ->join(
                    ['cpw' => $this->resourceConnection->getTableName('catalog_product_website')],
                    'e.entity_id = cpw.product_id',
                    []
                )
                ->where('e.entity_id IN (?)', $batchIds)
                ->where('cpw.website_id = ?', $this->storeManager->getStore()->getWebsiteId());

            $items = $connection->fetchAll($select);

            // Load product attributes
            $attributeValues = $this->loadAttributes($batchIds, $storeId);

            // Load stock status
            $stockStatus = $this->loadStockStatus($batchIds);

            // Load prices
            $prices = $this->loadPrices($batchIds, $storeId);

            // Merge all data
            foreach ($items as $item) {
                $productId = $item['entity_id'];
                // Pre-allocate array with known keys to avoid array_merge
                $result[$productId] = $item;
                if (isset($attributeValues[$productId])) {
                    foreach ($attributeValues[$productId] as $key => $value) {
                        $result[$productId][$key] = $value;
                    }
                }
                $result[$productId]['stock_status'] = $stockStatus[$productId] ?? false;
                $result[$productId]['price_data'] = $prices[$productId] ?? [];
            }
        }

        return $result;
    }

    private function loadAttributes(array $productIds, int $storeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $result = [];

        // Get frequently accessed attributes
        $attributes = ['name', 'url_key', 'status', 'visibility', 'description'];

        foreach ($attributes as $attributeCode) {
            $attributeId = $this->getAttributeId($attributeCode);
            if (!$attributeId) {
                continue;
            }

            // Get attribute values
            $select = $connection->select()
                ->from(
                    ['t' => $this->resourceConnection->getTableName('catalog_product_entity_varchar')],
                    ['entity_id', 'value']
                )
                ->where('t.attribute_id = ?', $attributeId)
                ->where('t.entity_id IN (?)', $productIds)
                ->where('t.store_id IN (?)', [0, $storeId]);

            $values = $connection->fetchPairs($select);

            foreach ($values as $productId => $value) {
                if (!isset($result[$productId])) {
                    $result[$productId] = [];
                }
                $result[$productId][$attributeCode] = $value;
            }
        }

        return $result;
    }

    private function loadStockStatus(array $productIds): array
    {
        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('cataloginventory_stock_status'),
                ['product_id', 'stock_status']
            )
            ->where('product_id IN (?)', $productIds);

        return $connection->fetchPairs($select);
    }

    private function loadPrices(array $productIds, int $storeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();

        $priceAttributeId = $this->getAttributeId('price');
        $specialPriceAttributeId = $this->getAttributeId('special_price');

        // Get regular prices
        $select = $connection->select()
            ->from(
                ['p' => $this->resourceConnection->getTableName('catalog_product_entity_decimal')],
                ['entity_id', 'value']
            )
            ->where('p.attribute_id = ?', $priceAttributeId)
            ->where('p.entity_id IN (?)', $productIds)
            ->where('p.store_id IN (?)', [0, $storeId]);

        $prices = $connection->fetchPairs($select);

        // Get special prices
        $select = $connection->select()
            ->from(
                ['sp' => $this->resourceConnection->getTableName('catalog_product_entity_decimal')],
                ['entity_id', 'value']
            )
            ->where('sp.attribute_id = ?', $specialPriceAttributeId)
            ->where('sp.entity_id IN (?)', $productIds)
            ->where('sp.store_id IN (?)', [0, $storeId]);

        $specialPrices = $connection->fetchPairs($select);

        $result = [];
        foreach ($productIds as $productId) {
            $result[$productId] = [
                'regular_price' => $prices[$productId] ?? null,
                'special_price' => $specialPrices[$productId] ?? null
            ];
        }

        return $result;
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
                ->where('entity_type_id = ?', 4); // 4 is catalog_product

            $attributeIds[$attributeCode] = $connection->fetchOne($select);
        }

        return $attributeIds[$attributeCode] ?: null;
    }

    protected function generateCacheKey(string $id): string
    {
        $storeId = $this->storeManager->getStore()->getId();
        return sprintf('frequent_product_%s_store_%d', $id, $storeId);
    }

    protected function getCacheTags(mixed $item): array
    {
        $tags = ['catalog_product'];
        if (isset($item['entity_id'])) {
            $tags[] = 'catalog_product_' . $item['entity_id'];
        }
        return $tags;
    }
}
