<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Cache;

/**
 * Service class for managing GraphQL cache tags
 *
 * This class handles cache tag generation and management for GraphQL queries
 * and entities. It maintains relationships between entities and their cache
 * tags, and provides methods for generating appropriate cache tags based on
 * query content and results.
 */
class TagManager
{
    /**
     * @var array<string, array{
     *     related_entities: array<string>,
     *     invalidation_events: array<string>
     * }> Entity tag configuration
     */
    private array $entityTags = [
        'catalog_product' => [
            'related_entities' => ['category', 'attribute'],
            'invalidation_events' => [
                'catalog_product_save_after',
                'catalog_product_delete_after',
                'cataloginventory_stock_item_save_after'
            ]
        ],
        'catalog_category' => [
            'related_entities' => ['product'],
            'invalidation_events' => [
                'catalog_category_save_after',
                'catalog_category_delete_after',
                'catalog_category_move_after'
            ]
        ],
        'customer' => [
            'related_entities' => ['customer_group'],
            'invalidation_events' => [
                'customer_save_after',
                'customer_delete_after',
                'customer_group_save_after'
            ]
        ],
        'cms_page' => [
            'related_entities' => ['store'],
            'invalidation_events' => [
                'cms_page_save_after',
                'cms_page_delete_after'
            ]
        ],
        'cms_block' => [
            'related_entities' => ['store'],
            'invalidation_events' => [
                'cms_block_save_after',
                'cms_block_delete_after'
            ]
        ]
    ];

    /**
     * Get cache tags for entity
     *
     * @param  string     $entityType
     * @param  string|int $entityId
     * @return array
     */
    public function getEntityTags(string $entityType, string|int $entityId): array
    {
        $tags = [];

        if (isset($this->entityTags[$entityType])) {
            // Add main entity tag
            $tags[] = $entityType;
            $tags[] = sprintf('%s_%s', $entityType, $entityId);

            // Add related entity tags
            foreach ($this->entityTags[$entityType]['related_entities'] as $relatedEntity) {
                $tags[] = $relatedEntity;
            }
        }

        return array_unique($tags);
    }

    /**
     * Get invalidation events for entity type
     *
     * @param  string $entityType
     * @return array
     */
    public function getInvalidationEvents(string $entityType): array
    {
        return $this->entityTags[$entityType]['invalidation_events'] ?? [];
    }

    /**
     * Get related entity types
     *
     * @param  string $entityType
     * @return array
     */
    public function getRelatedEntities(string $entityType): array
    {
        return $this->entityTags[$entityType]['related_entities'] ?? [];
    }

    /**
     * Get cache tags for query
     *
     * @param  string $query
     * @return array
     */
    public function getQueryTags(string $query): array
    {
        $tags = ['graphql_query'];

        // Add entity-specific tags based on query content
        if (stripos($query, 'products') !== false) {
            $tags[] = 'catalog_product';
        }
        if (stripos($query, 'categories') !== false) {
            $tags[] = 'catalog_category';
        }
        if (stripos($query, 'customer') !== false) {
            $tags[] = 'customer';
        }
        if (stripos($query, 'cmsPage') !== false) {
            $tags[] = 'cms_page';
        }
        if (stripos($query, 'cmsBlock') !== false) {
            $tags[] = 'cms_block';
        }

        return array_unique($tags);
    }

    /**
     * Get cache tags for result
     *
     * @param  array $result
     * @return array
     */
    public function getResultTags(array $result): array
    {
        $tags = ['graphql_query'];

        if (isset($result['data'])) {
            if (isset($result['data']['products'])) {
                $tags[] = 'catalog_product';
                foreach ($result['data']['products']['items'] ?? [] as $product) {
                    if (isset($product['id'])) {
                        $tags[] = 'catalog_product_' . $product['id'];
                    }
                }
            }

            if (isset($result['data']['categories'])) {
                $tags[] = 'catalog_category';
                foreach ($result['data']['categories']['items'] ?? [] as $category) {
                    if (isset($category['id'])) {
                        $tags[] = 'catalog_category_' . $category['id'];
                    }
                }
            }

            if (isset($result['data']['customer'])) {
                $tags[] = 'customer';
                if (isset($result['data']['customer']['id'])) {
                    $tags[] = 'customer_' . $result['data']['customer']['id'];
                }
            }

            if (isset($result['data']['cmsPage'])) {
                $tags[] = 'cms_page';
                if (isset($result['data']['cmsPage']['identifier'])) {
                    $tags[] = 'cms_page_' . $result['data']['cmsPage']['identifier'];
                }
            }

            if (isset($result['data']['cmsBlock'])) {
                $tags[] = 'cms_block';
                if (isset($result['data']['cmsBlock']['identifier'])) {
                    $tags[] = 'cms_block_' . $result['data']['cmsBlock']['identifier'];
                }
            }
        }

        return array_unique($tags);
    }
}
