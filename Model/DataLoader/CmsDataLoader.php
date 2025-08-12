<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\PromiseAdapter;
use Magento\Framework\ObjectManagerInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Data loader for CMS entities (pages and blocks)
 *
 * This class provides efficient loading of CMS data through batch loading.
 * It handles both pages and blocks, with support for store-specific content
 * and caching. The cache lifetime is extended for CMS data as it changes
 * less frequently than other entities.
 */
class CmsDataLoader extends FrequentDataLoader
{
    /**
     * @var int Maximum number of CMS entities to load in a single batch
     */
    private const BATCH_SIZE = 20;

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
        int $cacheLifetime = 86400 // 24 hours for CMS data
    ) {
        parent::__construct($objectManager, $cache, $promiseAdapter, $cacheLifetime);
    }

    /**
     * Load CMS data from database
     *
     * This method loads both CMS pages and blocks in batches. It handles the
     * separation of IDs by entity type (page/block) and delegates loading to
     * specialized methods.
     *
     * @param  array $ids CMS entity IDs (prefixed with 'page_' or 'block_')
     * @return array     CMS data indexed by ID
     */
    protected function loadFromDatabase(array $ids): array
    {
        $result = [];
        $storeId = $this->storeManager->getStore()->getId();

        // Split IDs into batches
        $batches = array_chunk($ids, self::BATCH_SIZE);

        foreach ($batches as $batchIds) {
            // Load pages
            $pageIds = array_filter($batchIds, fn($id) => strpos($id, 'page_') === 0);
            if (!empty($pageIds)) {
                $pages = $this->loadPages($pageIds, $storeId);
                foreach ($pages as $id => $page) {
                    $result[$id] = $page;
                }
            }

            // Load blocks
            $blockIds = array_filter($batchIds, fn($id) => strpos($id, 'block_') === 0);
            if (!empty($blockIds)) {
                $blocks = $this->loadBlocks($blockIds, $storeId);
                foreach ($blocks as $id => $block) {
                    $result[$id] = $block;
                }
            }
        }

        return $result;
    }

    /**
     * Load CMS pages in batch
     *
     * @param array $pageIds Array of page IDs (with 'page_' prefix)
     * @param int $storeId Store ID to load pages for
     * @return array<string, array> Loaded pages indexed by ID
     */
    private function loadPages(array $pageIds, int $storeId): array
    {
        $result = [];
        $pageIds = array_map(
            function ($id) {
                return str_replace('page_', '', $id);
            },
            $pageIds
        );

        $resourceConnection = $this->objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                ['p' => $resourceConnection->getTableName('cms_page')],
                [
                    'page_id',
                    'identifier',
                    'title',
                    'content',
                    'content_heading',
                    'meta_title',
                    'meta_keywords',
                    'meta_description',
                    'page_layout',
                    'layout_update_xml',
                    'custom_theme',
                    'custom_root_template'
                ]
            )
            ->where('p.page_id IN (?)', $pageIds)
            ->where('p.store_id IN (?)', [0, $storeId])
            ->where('p.is_active = ?', 1);

        try {
            $pages = $connection->fetchAll($select);
            foreach ($pages as $page) {
                $id = 'page_' . $page['page_id'];
                $result[$id] = array_merge($page, ['type' => 'page']);
            }
        } catch (\Exception $e) {
            // If page loading fails, we'll return empty results for those pages
            foreach ($pageIds as $pageId) {
                $id = 'page_' . $pageId;
                $result[$id] = [
                    'id' => $pageId,
                    'type' => 'page',
                    'error' => 'Failed to load page'
                ];
            }
        }

        return $result;
    }

    /**
     * Load CMS blocks in batch
     *
     * @param array $blockIds Array of block IDs (with 'block_' prefix)
     * @param int $storeId Store ID to load blocks for
     * @return array<string, array> Loaded blocks indexed by ID
     */
    private function loadBlocks(array $blockIds, int $storeId): array
    {
        $result = [];
        $blockIds = array_map(
            function ($id) {
                return str_replace('block_', '', $id);
            },
            $blockIds
        );

        $resourceConnection = $this->objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                ['b' => $resourceConnection->getTableName('cms_block')],
                [
                    'block_id',
                    'identifier',
                    'title',
                    'content'
                ]
            )
            ->where('b.block_id IN (?)', $blockIds)
            ->where('b.store_id IN (?)', [0, $storeId])
            ->where('b.is_active = ?', 1);

        try {
            $blocks = $connection->fetchAll($select);
            foreach ($blocks as $block) {
                $id = 'block_' . $block['block_id'];
                $result[$id] = array_merge($block, ['type' => 'block']);
            }
        } catch (\Exception $e) {
            // If block loading fails, we'll return empty results for those blocks
            foreach ($blockIds as $blockId) {
                $id = 'block_' . $blockId;
                $result[$id] = [
                    'id' => $blockId,
                    'type' => 'block',
                    'error' => 'Failed to load block'
                ];
            }
        }

        return $result;
    }

    /**
     * Generate cache key for CMS data
     *
     * This method generates a unique cache key for CMS data that includes both
     * the entity ID and store ID to ensure store-specific caching.
     *
     * @param  string $id CMS entity ID
     * @return string    Cache key
     */
    protected function generateCacheKey(string $id): string
    {
        $storeId = $this->storeManager->getStore()->getId();
        return sprintf('cms_%s_store_%d', $id, $storeId);
    }

    /**
     * Get cache tags for CMS data
     *
     * This method returns the cache tags used for cache invalidation. It includes
     * both general CMS tags and specific tags for pages and blocks.
     *
     * @param  mixed $item CMS data
     * @return array      Cache tags
     */
    protected function getCacheTags(mixed $item): array
    {
        $tags = ['cms'];
        if (is_array($item)) {
            if ($item['type'] === 'page') {
                $tags[] = 'cms_page';
                $tags[] = 'cms_page_' . $item['id'];
            } elseif ($item['type'] === 'block') {
                $tags[] = 'cms_block';
                $tags[] = 'cms_block_' . $item['id'];
            }
        }
        return $tags;
    }
}
