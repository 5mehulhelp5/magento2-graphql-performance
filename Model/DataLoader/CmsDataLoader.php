<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\PromiseAdapter;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

class CmsDataLoader extends FrequentDataLoader
{
    private const BATCH_SIZE = 20;
    private array $pageCache = [];
    private array $blockCache = [];

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
                $result = array_merge($result, $pages);
            }

            // Load blocks
            $blockIds = array_filter($batchIds, fn($id) => strpos($id, 'block_') === 0);
            if (!empty($blockIds)) {
                $blocks = $this->loadBlocks($blockIds, $storeId);
                $result = array_merge($result, $blocks);
            }
        }

        return $result;
    }

    private function loadPages(array $pageIds, int $storeId): array
    {
        $result = [];
        $pageIds = array_map(function ($id) {
            return str_replace('page_', '', $id);
        }, $pageIds);

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
            // Log error if needed
        }

        return $result;
    }

    private function loadBlocks(array $blockIds, int $storeId): array
    {
        $result = [];
        $blockIds = array_map(function ($id) {
            return str_replace('block_', '', $id);
        }, $blockIds);

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
            // Log error if needed
        }

        return $result;
    }

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

    protected function generateCacheKey(string $id): string
    {
        $storeId = $this->storeManager->getStore()->getId();
        return sprintf('cms_%s_store_%d', $id, $storeId);
    }

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
