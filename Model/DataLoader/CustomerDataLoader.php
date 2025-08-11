<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\PromiseAdapter;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Psr\Log\LoggerInterface;

/**
 * Data loader for customer entities with batch loading support
 */
class CustomerDataLoader extends FrequentDataLoader
{
    private const BATCH_SIZE = 50;

    public function __construct(
        PromiseAdapter $promiseAdapter,
        ResolverCache $cache,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ?LoggerInterface $logger = null,
        int $cacheLifetime = 3600
    ) {
        parent::__construct($promiseAdapter, $cache, $cacheLifetime);
    }

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

    use CacheKeyGeneratorTrait;

    protected function getEntityType(): string
    {
        return 'customer';
    }
}
