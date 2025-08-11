<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface RepositoryInterface
{
    /**
     * Get list of items by search criteria
     *
     * @param  SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Get item by ID
     *
     * @param  int $id
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): mixed;

    /**
     * Get entity type code
     *
     * @return string
     */
    public function getEntityType(): string;
}
