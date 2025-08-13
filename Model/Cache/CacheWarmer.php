<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Cache;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\GraphQl\Schema;
use Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface;

/**
 * Service class for warming GraphQL cache
 *
 * This class executes predefined GraphQL queries to populate the cache with
 * frequently accessed data, improving response times for subsequent requests.
 * It supports both default and custom warming patterns per store view.
 */
class CacheWarmer
{
    /**
     * @var array<string, string> Default cache warming queries
     */
    private array $defaultQueries = [
        'categories' => '
            query GetCategories {
                categories {
                    items {
                        id
                        name
                        url_key
                        children {
                            id
                            name
                            url_key
                        }
                    }
                }
            }
        ',
        'featured_products' => '
            query GetFeaturedProducts {
                products(
                    filter: { category_id: { eq: "2" } }
                    pageSize: 12
                    currentPage: 1
                ) {
                    items {
                        id
                        name
                        sku
                        url_key
                        price_range {
                            minimum_price {
                                regular_price {
                                    value
                                    currency
                                }
                                final_price {
                                    value
                                    currency
                                }
                            }
                        }
                        stock_status
                    }
                }
            }
        ',
        'cms_pages' => '
            query GetCmsPages {
                cmsPages {
                    items {
                        identifier
                        title
                        content
                        meta_title
                        meta_keywords
                        meta_description
                    }
                }
            }
        '
    ];

    /**
     * @param QueryProcessor $queryProcessor GraphQL query processor
     * @param StoreManagerInterface $storeManager Store manager
     * @param ScopeConfigInterface $scopeConfig Configuration reader
     * @param LoggerInterface $logger Logger service
     * @param SchemaGeneratorInterface $schemaGenerator Schema generator
     */
    public function __construct(
        private readonly QueryProcessor $queryProcessor,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly SchemaGeneratorInterface $schemaGenerator
    ) {
    }

    /**
     * Warm up cache for all stores
     *
     * @return void
     */
    public function warmupCache(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $this->warmupStoreCache((int) $store->getId());
        }
    }

    /**
     * Warm up cache for specific store
     *
     * @param  int $storeId
     * @return void
     */
    public function warmupStoreCache(int $storeId): void
    {
        $this->logger->info(sprintf('Starting cache warmup for store ID: %d', $storeId));

        // Get custom queries from configuration
        $customQueries = $this->getCustomQueries($storeId);
        $queries = array_merge($this->defaultQueries, $customQueries);

        foreach ($queries as $queryName => $query) {
            try {
                $this->executeQuery($query, $storeId);
                $this->logger->info(
                    sprintf(
                        'Successfully warmed up cache for query "%s" in store %d',
                        $queryName,
                        $storeId
                    )
                );
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        'Failed to warm up cache for query "%s" in store %d: %s',
                        $queryName,
                        $storeId,
                        $e->getMessage()
                    )
                );
            }
        }

        $this->logger->info(sprintf('Completed cache warmup for store ID: %d', $storeId));
    }

    /**
     * Execute GraphQL query
     *
     * @param  string $query
     * @param  int    $storeId
     * @return void
     */
    private function executeQuery(string $query, int $storeId): void
    {
        // Get the schema for the current store
        $schema = $this->schemaGenerator->generate();

        // Execute the query with the correct parameter order
        $this->queryProcessor->process(
            $schema,
            $query,
            null, // operationName
            [], // variables
            null, // context
            [ // extensions
                'store_id' => $storeId,
                'cache_warmup' => true
            ]
        );
    }

    /**
     * Get custom queries from configuration
     *
     * @param  int $storeId
     * @return array
     */
    private function getCustomQueries(int $storeId): array
    {
        $queries = [];
        $customQueries = $this->scopeConfig->getValue(
            'graphql_performance/cache/cache_warming_patterns',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!empty($customQueries) && is_array($customQueries)) {
            foreach ($customQueries as $name => $query) {
                if (!empty($query)) {
                    $queries[$name] = $query;
                }
            }
        }

        return $queries;
    }
}
