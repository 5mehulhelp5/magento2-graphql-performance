<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\Categories;

/**
 * Trait for handling pagination in GraphQL resolvers
 *
 * This trait provides utility methods for handling pagination in GraphQL
 * query results. It formats the data according to the Magento GraphQL
 * pagination structure.
 */
trait PaginationTrait
{
    /**
     * Get paginated result in Magento GraphQL format
     *
     * @param array $items Items to paginate
     * @param int $totalCount Total number of items
     * @param array $args Pagination arguments
     * @return array Paginated result with page info
     */
    protected function getPaginatedResult(array $items, int $totalCount, array $args): array
    {
        $pageSize = $args['pageSize'] ?? 20;
        $currentPage = $args['currentPage'] ?? 1;
        $totalPages = ceil($totalCount / $pageSize);

        return [
            'total_count' => $totalCount,
            'items' => $items,
            'page_info' => [
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'has_next_page' => $currentPage < $totalPages,
                'has_previous_page' => $currentPage > 1
            ]
        ];
    }
}
