# GraphQL Performance Module Examples

## Query Examples

### 1. Product Queries with DataLoader

```graphql
query GetProducts {
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
      # Price data loaded efficiently via DataLoader
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
      # Stock data batch loaded
      stock_status
      # Custom attributes loaded in batches
      custom_attributes {
        attribute_code
        value
      }
    }
    # Efficient total count calculation
    total_count
  }
}
```

### 2. Category Tree with Optimized Loading

```graphql
query GetCategoryTree {
  categories {
    items {
      id
      name
      url_key
      # Children loaded efficiently
      children {
        id
        name
        url_key
        # Product counts calculated in batch
        product_count
        # Breadcrumbs generated efficiently
        breadcrumbs {
          category_id
          category_name
          category_url_key
        }
      }
      # Custom attributes batch loaded
      custom_attributes {
        attribute_code
        value
      }
    }
  }
}
```

### 3. Customer Data with Caching

```graphql
query GetCustomerData {
  customer {
    # Basic info cached
    firstname
    lastname
    email
    # Orders loaded with DataLoader
    orders {
      items {
        order_number
        created_at
        # Items batch loaded
        items {
          product_name
          qty_ordered
          price
        }
        # Payment info cached
        payment_methods {
          name
          type
        }
        # Shipping info batch loaded
        shipping_address {
          firstname
          lastname
          street
          city
          postcode
        }
      }
    }
  }
}
```

## Implementation Examples

### 1. Custom DataLoader Implementation

```php
namespace Sterk\GraphQlPerformance\Model\DataLoader;

use GraphQL\Executor\Promise\PromiseAdapter;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

class CustomEntityDataLoader extends BatchDataLoader
{
    private const BATCH_SIZE = 50;
    
    public function __construct(
        PromiseAdapter $promiseAdapter,
        ResolverCache $cache,
        private readonly CustomEntityRepository $repository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        int $cacheLifetime = 3600
    ) {
        parent::__construct($promiseAdapter, $cache, $cacheLifetime);
    }

    protected function loadFromDatabase(array $ids): array
    {
        $batches = array_chunk($ids, self::BATCH_SIZE);
        $result = [];

        foreach ($batches as $batchIds) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_id', $batchIds, 'in')
                ->create();
            
            $entities = $this->repository->getList($searchCriteria)->getItems();
            foreach ($entities as $entity) {
                $result[$entity->getId()] = $entity;
            }
        }

        return $result;
    }

    protected function generateCacheKey(string $id): string
    {
        return sprintf('custom_entity_%s', $id);
    }

    protected function getCacheTags(mixed $item): array
    {
        return ['custom_entity', 'custom_entity_' . $item->getId()];
    }
}
```

### 2. Custom Resolver with Caching and Batching

```php
namespace Sterk\GraphQlPerformance\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class CustomResolver implements ResolverInterface
{
    public function __construct(
        private readonly CustomEntityDataLoader $dataLoader,
        private readonly ResolverCache $cache,
        private readonly TagManager $tagManager
    ) {}

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $cacheKey = $this->generateCacheKey($args);
        $cachedData = $this->cache->get($cacheKey);
        
        if ($cachedData !== null) {
            return $cachedData;
        }

        $result = $this->dataLoader->loadMany($args['ids']);
        
        $tags = $this->tagManager->getEntityTags('custom_entity', $args['ids']);
        $this->cache->set($cacheKey, $result, $tags);
        
        return $result;
    }

    private function generateCacheKey(array $args): string
    {
        return sprintf(
            'custom_resolver_%s',
            md5(json_encode($args))
        );
    }
}
```

### 3. Performance Monitoring Implementation

```php
namespace Sterk\GraphQlPerformance\Plugin;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;

class PerformanceMonitorPlugin
{
    public function __construct(
        private readonly QueryTimer $queryTimer,
        private readonly MetricsCollector $metricsCollector
    ) {}

    public function beforeProcess(
        QueryProcessor $subject,
        string $query,
        ?array $variables = null,
        ?array $context = null
    ): array {
        $this->queryTimer->start($query);
        return [$query, $variables, $context];
    }

    public function afterProcess(
        QueryProcessor $subject,
        array $result,
        string $query
    ): array {
        $executionTime = $this->queryTimer->stop($query);
        
        $this->metricsCollector->recordMetrics([
            'query_hash' => md5($query),
            'execution_time' => $executionTime,
            'cache_hits' => $this->queryTimer->getCacheHits(),
            'database_queries' => $this->queryTimer->getDatabaseQueries()
        ]);
        
        return $result;
    }
}
```

## Cache Warming Examples

### 1. Custom Cache Warming Pattern

```php
namespace Sterk\GraphQlPerformance\Cron;

class CustomCacheWarming
{
    public function __construct(
        private readonly CacheWarmer $cacheWarmer,
        private readonly StoreManagerInterface $storeManager
    ) {}

    public function execute(): void
    {
        $queries = [
            'popular_products' => '
                query {
                    products(
                        filter: { is_popular: { eq: "1" } }
                        pageSize: 20
                    ) {
                        items {
                            id
                            name
                            price_range {
                                minimum_price {
                                    final_price {
                                        value
                                    }
                                }
                            }
                        }
                    }
                }
            ',
            'main_categories' => '
                query {
                    categories(
                        filters: { parent_id: { eq: "2" } }
                    ) {
                        items {
                            id
                            name
                            children {
                                id
                                name
                            }
                        }
                    }
                }
            '
        ];

        foreach ($this->storeManager->getStores() as $store) {
            $this->cacheWarmer->warmupQueries($queries, $store->getId());
        }
    }
}
```

## Performance Optimization Examples

### 1. Query Complexity Configuration

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <type name="Sterk\GraphQlPerformance\Model\QueryComplexity\ComplexityCalculator">
        <arguments>
            <argument name="fieldComplexity" xsi:type="array">
                <item name="products" xsi:type="number">10</item>
                <item name="categories" xsi:type="number">5</item>
                <item name="customer" xsi:type="number">3</item>
            </argument>
            <argument name="maxQueryComplexity" xsi:type="number">1000</argument>
            <argument name="maxQueryDepth" xsi:type="number">10</argument>
        </arguments>
    </type>
</config>
```

### 2. Connection Pool Configuration

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <type name="Sterk\GraphQlPerformance\Model\ResourceConnection\ConnectionPool">
        <arguments>
            <argument name="maxConnections" xsi:type="number">50</argument>
            <argument name="minConnections" xsi:type="number">5</argument>
            <argument name="idleTimeout" xsi:type="number">300</argument>
        </arguments>
    </type>
</config>
```
