<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\PromiseAdapter;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Psr\Log\LoggerInterface;

/**
 * Data loader for customer entities with batch loading support
 */
class CustomerDataLoader extends FrequentDataLoader
{
    /**
     * @var int Maximum number of customers to load in a single batch
     */
    private const BATCH_SIZE = 50;

    /**
     * @param ObjectManagerInterface $objectManager Object manager for lazy loading
     * @param ResolverCache $cache Cache service
     * @param PromiseAdapter $promiseAdapter GraphQL promise adapter
     * @param CustomerRepositoryInterface $customerRepository Customer repository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder Search criteria builder
     * @param LoggerInterface|null $logger Logger service
     * @param int $cacheLifetime Cache lifetime in seconds
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ResolverCache $cache,
        PromiseAdapter $promiseAdapter,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ?LoggerInterface $logger = null,
        int $cacheLifetime = 3600
    ) {
        parent::__construct($objectManager, $cache, $promiseAdapter, $cacheLifetime);
    }

    /**
     * Load customers from database in batches
     *
     * This method loads customers in batches to optimize database queries.
     * It handles missing customers gracefully and logs warnings when needed.
     *
     * @param array $ids Array of customer IDs
     * @return array<int, \Magento\Customer\Api\Data\CustomerInterface> Loaded customers indexed by ID
     */
    protected function loadFromDatabase(array $ids): array
    {
        // Split IDs into batches
        $batches = array_chunk($ids, self::BATCH_SIZE);
        $result = [];

        foreach ($batches as $batchIds) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_id', $batchIds, 'in')
                ->create();

            try {
                $customers = $this->customerRepository->getList($searchCriteria)->getItems();
                foreach ($customers as $customer) {
                    $result[$customer->getId()] = $customer;
                }
            } catch (NoSuchEntityException $e) {
                // Silently handle missing customers to avoid breaking the GraphQL response
                // Missing customers will be reflected in the result array
                $this->logger?->warning('Customer(s) not found: ' . $e->getMessage(), [
                    'customer_ids' => $batchIds,
                    'exception' => $e
                ]);
            }
        }

        return $result;
    }

    /**
     * Generate cache key for customer data
     *
     * @param string $id Customer ID
     * @return string Cache key
     */
    protected function generateCacheKey(string $id): string
    {
        return sprintf('customer_%s', $id);
    }

    /**
     * Get cache tags for customer data
     *
     * @param mixed $item Customer data
     * @return array Cache tags
     */
    protected function getCacheTags(mixed $item): array
    {
        $tags = ['customer'];
        if ($item instanceof \Magento\Customer\Api\Data\CustomerInterface) {
            $tags[] = 'customer_' . $item->getId();
        }
        return $tags;
    }
}
