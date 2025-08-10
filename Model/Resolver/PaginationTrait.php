<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver;

trait PaginationTrait
{
    /**
     * Get page size from arguments
     *
     * @param array $args
     * @param int $default
     * @return int
     */
    protected function getPageSize(array $args, int $default = 20): int
    {
        return $args['pageSize'] ?? $default;
    }

    /**
     * Get current page from arguments
     *
     * @param array $args
     * @param int $default
     * @return int
     */
    protected function getCurrentPage(array $args, int $default = 1): int
    {
        return $args['currentPage'] ?? $default;
    }

    /**
     * Get page info
     *
     * @param int $totalCount
     * @param int $pageSize
     * @param int $currentPage
     * @return array
     */
    protected function getPageInfo(int $totalCount, int $pageSize, int $currentPage): array
    {
        return [
            'total_pages' => ceil($totalCount / $pageSize),
            'current_page' => $currentPage,
            'page_size' => $pageSize
        ];
    }

    /**
     * Get result with pagination
     *
     * @param array $items
     * @param int $totalCount
     * @param array $args
     * @return array
     */
    protected function getPaginatedResult(array $items, int $totalCount, array $args): array
    {
        $pageSize = $this->getPageSize($args);
        $currentPage = $this->getCurrentPage($args);

        return [
            'items' => $items,
            'total_count' => $totalCount,
            'page_info' => $this->getPageInfo($totalCount, $pageSize, $currentPage)
        ];
    }
}
