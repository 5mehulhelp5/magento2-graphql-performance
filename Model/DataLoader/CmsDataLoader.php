<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\PromiseAdapter;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

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
     * @var array<string, \Magento\Cms\Api\Data\PageInterface> Cache of CMS pages by ID
     */
    private array $pageCache = [];

    /**
     * @var array<string, \Magento\Cms\Api\Data\BlockInterface> Cache of CMS blocks by ID
     */
    private array $blockCache = [];

    /**
     * @param PromiseAdapter $promiseAdapter GraphQL promise adapter
     * @param ResolverCache $cache Cache service
     * @param PageRepositoryInterface $pageRepository Page repository
     * @param BlockRepositoryInterface $blockRepository Block repository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder Search criteria builder
     * @param StoreManagerInterface $storeManager Store manager
     * @param int $cacheLifetime Cache lifetime in seconds
     */
    public function __construct(
        PromiseAdapter $promiseAdapter,
        ResolverCache $cache,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly StoreManagerInterface $storeManager,
        int $cacheLifetime = 86400 // 24 hours for CMS data
    ) {
        parent::__construct($promiseAdapter, $cache, $cacheLifetime);
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

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('page_id', $pageIds, 'in')
            ->addFilter('store_id', [0, $storeId], 'in')
            ->addFilter('is_active', 1)
            ->create();

        try {
            $pages = $this->pageRepository->getList($searchCriteria)->getItems();
            foreach ($pages as $page) {
                $id = 'page_' . $page->getId();
                $result[$id] = $this->transformPageData($page);
                $this->pageCache[$id] = $page;
            }
        } catch (\Exception $e) {
            // If page loading fails, we'll return empty results for those pages
            // This prevents the entire request from failing due to individual page issues
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

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('block_id', $blockIds, 'in')
            ->addFilter('store_id', [0, $storeId], 'in')
            ->addFilter('is_active', 1)
            ->create();

        try {
            $blocks = $this->blockRepository->getList($searchCriteria)->getItems();
            foreach ($blocks as $block) {
                $id = 'block_' . $block->getId();
                $result[$id] = $this->transformBlockData($block);
                $this->blockCache[$id] = $block;
            }
        } catch (\Exception $e) {
            // If block loading fails, we'll return empty results for those blocks
            // This prevents the entire request from failing due to individual block issues
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
     * Transform CMS page data to GraphQL format
     *
     * @param \Magento\Cms\Api\Data\PageInterface $page CMS page entity
     * @return array Transformed page data
     */
    private function transformPageData($page): array
    {
        return [
            'id' => $page->getId(),
            'identifier' => $page->getIdentifier(),
            'title' => $page->getTitle(),
            'content' => $page->getContent(),
            'content_heading' => $page->getContentHeading(),
            'meta_title' => $page->getMetaTitle(),
            'meta_keywords' => $page->getMetaKeywords(),
            'meta_description' => $page->getMetaDescription(),
            'page_layout' => $page->getPageLayout(),
            'layout_update_xml' => $page->getLayoutUpdateXml(),
            'custom_theme' => $page->getCustomTheme(),
            'custom_root_template' => $page->getCustomRootTemplate(),
            'type' => 'page'
        ];
    }

    /**
     * Transform CMS block data to GraphQL format
     *
     * @param \Magento\Cms\Api\Data\BlockInterface $block CMS block entity
     * @return array Transformed block data
     */
    private function transformBlockData($block): array
    {
        return [
            'id' => $block->getId(),
            'identifier' => $block->getIdentifier(),
            'title' => $block->getTitle(),
            'content' => $block->getContent(),
            'type' => 'block'
        ];
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
