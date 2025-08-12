<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\PromiseAdapter;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Data loader for frequently accessed product data
 *
 * This class provides optimized loading of product data, including attributes,
 * stock status, and prices. It implements batch loading and caching strategies
 * to improve performance when loading data for multiple products.
 */
class FrequentProductDataLoader extends FrequentDataLoader
{
    /**
     * @var int Maximum number of products to load in a single batch
     */
    private const BATCH_SIZE = 100;

    /**
     * @param PromiseAdapter $promiseAdapter GraphQL promise adapter
     * @param ResolverCache $cache Cache service
     * @param ResourceConnection $resourceConnection Database connection
     * @param StoreManagerInterface $storeManager Store manager service
     * @param int $cacheLifetime Cache lifetime in seconds
     */
    public function __construct(
        PromiseAdapter $promiseAdapter,
        ResolverCache $cache,
        ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        int $cacheLifetime = 3600
    ) {
        parent::__construct($promiseAdapter, $cache, $resourceConnection, $cacheLifetime);
    }

    /**
     * Load product data from database
     *
     * This method loads product data in batches, including base data, attributes,
     * stock status, and prices. It optimizes database queries by using batch
     * loading and efficient joins.
     *
     * @param  array $ids Product IDs to load
     * @return array     Product data indexed by ID
     */
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

    /**
     * Load product attributes from database
     *
     * This method loads frequently accessed product attributes like name, URL key,
     * status, etc. It handles store-specific values and fallbacks to default
     * values when needed.
     *
     * @param  array $productIds Product IDs to load attributes for
     * @param  int   $storeId    Store view ID
     * @return array             Attribute values indexed by product ID
     */
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

    /**
     * Load stock status for products
     *
     * This method retrieves the stock status (in stock/out of stock) for products.
     * It uses the cataloginventory_stock_status table for efficient lookups.
     *
     * @param  array $productIds Product IDs to load stock status for
     * @return array            Stock status indexed by product ID
     */
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

    /**
     * Load prices for products
     *
     * This method loads both regular and special prices for products. It handles
     * store-specific pricing and fallbacks to default (website) prices when needed.
     *
     * @param  array $productIds Product IDs to load prices for
     * @param  int   $storeId    Store view ID
     * @return array            Price data indexed by product ID
     */
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

    /**
     * Generate cache key for product data
     *
     * This method generates a unique cache key for product data that includes
     * both the product ID and store ID to ensure store-specific caching.
     *
     * @param  string $id Product ID
     * @return string    Cache key
     */
    protected function generateCacheKey(string $id): string
    {
        $storeId = $this->storeManager->getStore()->getId();
        return sprintf('frequent_product_%s_store_%d', $id, $storeId);
    }

    /**
     * Get cache tags for product data
     *
     * This method returns the cache tags used for cache invalidation. It includes
     * both a general product tag and a specific tag for the individual product.
     *
     * @param  mixed $item Product data
     * @return array      Cache tags
     */
    protected function getCacheTags(mixed $item): array
    {
        $tags = ['catalog_product'];
        if (isset($item['entity_id'])) {
            $tags[] = 'catalog_product_' . $item['entity_id'];
        }
        return $tags;
    }
}
