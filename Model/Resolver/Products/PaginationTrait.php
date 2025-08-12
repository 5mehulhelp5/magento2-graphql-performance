<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\Products;

trait PaginationTrait
{
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
                'total_pages' => $totalPages
            ]
        ];
    }
}
