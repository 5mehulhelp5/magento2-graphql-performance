# API Documentation

## Base Classes and Traits

### AbstractResolver
The base class for all GraphQL resolvers, providing common functionality for caching, timing, and error handling.

```php
abstract class AbstractResolver implements BatchServiceContractResolverInterface
{
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = [], array $args = []): array;
    abstract protected function getEntityType(): string;
    abstract protected function getCacheTags(): array;
    abstract protected function resolveData(Field $field, $context, ResolveInfo $info, array $value = [], array $args = []): array;
}
```

#### Usage Example
```php
class ProductResolver extends AbstractResolver
{
    protected function getEntityType(): string
    {
        return 'product';
    }

    protected function getCacheTags(): array
    {
        return ['catalog_product'];
    }

    protected function resolveData(Field $field, $context, ResolveInfo $info, array $value = [], array $args = []): array
    {
        // Implement resolver logic
    }
}
```

### FieldSelectionTrait
A trait for handling GraphQL field selection and conditional data loading.

```php
trait FieldSelectionTrait
{
    protected function isFieldRequested(ResolveInfo $info, string $field): bool;
    protected function getRequestedFields(ResolveInfo $info, array $defaultFields = []): array;
    protected function addFieldIfRequested(array $result, ResolveInfo $info, string $field, $value): array;
}
```

#### Usage Example
```php
class CategoryResolver extends AbstractResolver
{
    use FieldSelectionTrait;

    protected function resolveData($field, $context, $info, $value, $args): array
    {
        $result = $this->getBaseData();
        
        if ($this->isFieldRequested($info, 'products')) {
            $result['products'] = $this->loadProducts();
        }
        
        return $result;
    }
}
```

### PaginationTrait
A trait for standardizing pagination handling across resolvers.

```php
trait PaginationTrait
{
    protected function getPageSize(array $args, int $default = 20): int;
    protected function getCurrentPage(array $args, int $default = 1): int;
    protected function getPageInfo(int $totalCount, int $pageSize, int $currentPage): array;
    protected function getPaginatedResult(array $items, int $totalCount, array $args): array;
}
```

#### Usage Example
```php
class ProductsResolver extends AbstractResolver
{
    use PaginationTrait;

    protected function resolveData($field, $context, $info, $value, $args): array
    {
        $items = $this->loadItems($args);
        $totalCount = $this->getTotalCount();
        
        return $this->getPaginatedResult($items, $totalCount, $args);
    }
}
```

### CacheKeyGeneratorTrait
A trait for standardizing cache key generation.

```php
trait CacheKeyGeneratorTrait
{
    protected function generateCacheKey(string $id): string;
    protected function getCacheTags(mixed $item): array;
    abstract protected function getEntityType(): string;
}
```

#### Usage Example
```php
class CustomerDataLoader extends FrequentDataLoader
{
    use CacheKeyGeneratorTrait;

    protected function getEntityType(): string
    {
        return 'customer';
    }
}
```

## Data Loaders

### BatchDataLoader
Base class for implementing the DataLoader pattern for batch loading.

```php
abstract class BatchDataLoader
{
    public function load($id);
    public function loadMany(array $ids): array;
    abstract protected function batchLoad(array $ids): array;
}
```

### FrequentDataLoader
Extended DataLoader with caching support for frequently accessed data.

```php
abstract class FrequentDataLoader extends BatchDataLoader
{
    public function load(string $id): Promise;
    abstract protected function loadFromDatabase(array $ids): array;
}
```

## Repository Pattern

### RepositoryInterface
Interface for standardizing repository implementations.

```php
interface RepositoryInterface
{
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
    public function getById(int $id);
    public function getEntityType(): string;
}
```

### AbstractRepositoryAdapter
Base class for repository implementations with common functionality.

```php
abstract class AbstractRepositoryAdapter implements RepositoryInterface
{
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
    public function getById(int $id);
    public function getByIds(array $ids): array;
}
```
