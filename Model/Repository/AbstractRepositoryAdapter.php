<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Repository;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Sterk\GraphQlPerformance\Api\RepositoryInterface;

/**
 * Abstract base class for repository adapters
 *
 * This class provides common functionality for repository adapters, including
 * batch loading capabilities and standardized repository operations. It serves
 * as a base for entity-specific repository adapters.
 */
abstract class AbstractRepositoryAdapter implements RepositoryInterface
{
    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder Search criteria builder
     * @param RepositoryInterface $repository Underlying repository
     */
    public function __construct(
        protected readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        protected readonly RepositoryInterface $repository
    ) {
    }

    /**
     * Get list of items by search criteria
     *
     * @param  SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        return $this->repository->getList($searchCriteria);
    }

    /**
     * Get item by ID
     *
     * @param  int $id
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): mixed
    {
        return $this->repository->getById($id);
    }

    /**
     * Get entity type code
     *
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->repository->getEntityType();
    }

    /**
     * Get items by IDs
     *
     * @param  array $ids
     * @return array
     */
    public function getByIds(array $ids): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $ids, 'in')
            ->create();

        $items = $this->getList($searchCriteria)->getItems();

        $result = [];
        foreach ($items as $item) {
            $result[$item->getId()] = $item;
        }

        return $result;
    }
}
