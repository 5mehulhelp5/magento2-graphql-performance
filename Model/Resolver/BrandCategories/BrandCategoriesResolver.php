<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\BrandCategories;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\DataLoader\BrandCategoryDataLoader;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;

class BrandCategoriesResolver implements ResolverInterface
{
    public function __construct(
        private readonly BrandCategoryDataLoader $brandCategoryDataLoader,
        private readonly ResolverCache $cache,
        private readonly QueryTimer $queryTimer
    ) {
    }

    /**
     * Resolve brand categories
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array|null  $value
     * @param  array|null  $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $this->queryTimer->start($info->operation->name->value, $info->operation->loc->source->body);

        try {
            // Try to get from cache first
            $cacheKey = $this->generateCacheKey($field, $context, $info, $value, $args);
            $cachedData = $this->cache->get($cacheKey);

            if ($cachedData !== null) {
                $this->queryTimer->stop($info->operation->name->value, $info->operation->loc->source->body, true);
                return $cachedData;
            }

            // Load all brand categories
            $brandCategories = $this->brandCategoryDataLoader->loadAllBrands();

            // Process pagination
            $pageSize = $args['pageSize'] ?? 20;
            $currentPage = $args['currentPage'] ?? 1;

            // Transform and paginate results
            $result = $this->processCategories($brandCategories, $pageSize, $currentPage);

            // Cache the result
            $this->cache->set(
                $cacheKey,
                $result,
                ['catalog_category', 'brand_category'],
                3600 // 1 hour cache
            );

            $this->queryTimer->stop($info->operation->name->value, $info->operation->loc->source->body);

            return $result;
        } catch (\Exception $e) {
            $this->queryTimer->stop($info->operation->name->value, $info->operation->loc->source->body);
            throw $e;
        }
    }

    /**
     * Process categories and apply pagination
     *
     * @param  array $categories
     * @param  int   $pageSize
     * @param  int   $currentPage
     * @return array
     */
    private function processCategories(array $categories, int $pageSize, int $currentPage): array
    {
        $letters = [];
        $processedCategories = [];

        foreach ($categories as $category) {
            $categoryName = $category->getName();
            $firstLetter = mb_strtoupper(mb_substr($categoryName, 0, 1, 'UTF-8'), 'UTF-8');
            $letters[$firstLetter] = $firstLetter;

            $processedCategories[] = [
                'id' => $category->getId(),
                'uid' => base64_encode('category/' . $category->getId()),
                'name' => $categoryName,
                'url_path' => $category->getUrlPath(),
                'thumbnail' => $category->getThumbnail()
            ];
        }

        // Sort categories by name
        usort(
            $processedCategories,
            function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            }
        );

        // Sort letters
        ksort($letters);

        // Apply pagination
        $totalCount = count($processedCategories);
        $totalPages = ceil($totalCount / $pageSize);
        $currentPage = min($currentPage, $totalPages);
        $offset = ($currentPage - 1) * $pageSize;

        $paginatedCategories = array_slice($processedCategories, $offset, $pageSize);

        return [
            'items' => $paginatedCategories,
            'total_count' => (string)$totalCount,
            'letters' => array_values($letters),
            'page_info' => [
                'total_pages' => $totalPages,
                'current_page' => $currentPage,
                'page_size' => $pageSize
            ]
        ];
    }

    /**
     * Generate cache key
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array|null  $value
     * @param  array|null  $args
     * @return string
     */
    private function generateCacheKey(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value,
        ?array $args
    ): string {
        $keyParts = [
            'brand_categories',
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
}
