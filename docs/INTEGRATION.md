# Integration Guide

## Overview
This guide provides detailed information about integrating the GraphQL Performance module into your Magento 2 project.

## Basic Integration

### 1. Custom Resolver Integration

```php
use Sterk\GraphQlPerformance\Model\Resolver\AbstractResolver;
use Sterk\GraphQlPerformance\Model\Resolver\FieldSelectionTrait;
use Sterk\GraphQlPerformance\Model\Resolver\PaginationTrait;

class CustomResolver extends AbstractResolver
{
    use FieldSelectionTrait;
    use PaginationTrait;

    protected function getEntityType(): string
    {
        return 'custom_entity';
    }

    protected function getCacheTags(): array
    {
        return ['custom_entity'];
    }

    protected function resolveData(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = [],
        array $args = []
    ): array {
        // Your resolver logic here
    }
}
```

### 2. DataLoader Integration

```php
use Sterk\GraphQlPerformance\Model\DataLoader\BatchDataLoader;

class CustomDataLoader extends BatchDataLoader
{
    protected function batchLoad(array $ids): array
    {
        // Your batch loading logic here
    }
}
```

### 3. Cache Integration

```php
use Sterk\GraphQlPerformance\Model\Cache\CacheKeyGeneratorTrait;

class CustomCacheManager
{
    use CacheKeyGeneratorTrait;

    protected function getEntityType(): string
    {
        return 'custom_entity';
    }
}
```

## Advanced Integration

### 1. Custom Performance Monitoring

```php
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;

class CustomMonitor
{
    public function __construct(
        private readonly QueryTimer $queryTimer
    ) {}

    public function trackOperation(string $operation): void
    {
        $this->queryTimer->start($operation);
        try {
            // Your operation logic
        } finally {
            $this->queryTimer->stop($operation);
        }
    }
}
```

### 2. Custom Repository Integration

```php
use Sterk\GraphQlPerformance\Model\Repository\AbstractRepositoryAdapter;

class CustomRepositoryAdapter extends AbstractRepositoryAdapter
{
    public function getEntityType(): string
    {
        return 'custom_entity';
    }
}
```

### 3. Event Observer Integration

```php
use Magento\Framework\Event\ObserverInterface;

class CustomPerformanceObserver implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $event = $observer->getEvent();
        // Your performance monitoring logic
    }
}
```

## GraphQL Schema Integration

### 1. Define Custom Types

```graphql
type CustomEntity @doc(description: "Custom entity type") {
    id: Int! @doc(description: "Entity ID")
    name: String! @doc(description: "Entity name")
    created_at: String! @doc(description: "Creation timestamp")
    # Add your custom fields
}

type CustomEntityResponse {
    items: [CustomEntity]! @doc(description: "Array of custom entities")
    total_count: Int! @doc(description: "Total count of custom entities")
    page_info: SearchResultPageInfo! @doc(description: "Pagination information")
}
```

### 2. Define Query

```graphql
type Query {
    customEntities(
        filter: CustomEntityFilterInput
        pageSize: Int = 20
        currentPage: Int = 1
    ): CustomEntityResponse
    @resolver(class: "\\Vendor\\Module\\Model\\Resolver\\CustomEntitiesResolver")
    @doc(description: "Get list of custom entities")
}
```

## Performance Optimization

### 1. Batch Loading Configuration

```xml
<type name="Vendor\Module\Model\CustomDataLoader">
    <arguments>
        <argument name="batchSize" xsi:type="number">100</argument>
        <argument name="cacheLifetime" xsi:type="number">3600</argument>
    </arguments>
</type>
```

### 2. Cache Configuration

```xml
<type name="Vendor\Module\Model\Cache\CustomCache">
    <arguments>
        <argument name="cacheFrontendPool" xsi:type="object">Magento\Framework\App\Cache\Frontend\Pool</argument>
        <argument name="identifier" xsi:type="string">custom_cache</argument>
        <argument name="tags" xsi:type="array">
            <item name="custom_entity" xsi:type="string">CUSTOM_ENTITY</item>
        </argument>
    </arguments>
</type>
```

## Security Integration

### 1. Query Validation

```php
use Sterk\GraphQlPerformance\Model\Security\RequestValidator;

class CustomQueryValidator
{
    public function __construct(
        private readonly RequestValidator $requestValidator
    ) {}

    public function validate(string $query): void
    {
        $this->requestValidator->validate(
            $this->getRequest(),
            $query,
            []
        );
    }
}
```

### 2. Rate Limiting

```php
use Sterk\GraphQlPerformance\Model\Security\RateLimiter;

class CustomRateLimiter
{
    public function __construct(
        private readonly RateLimiter $rateLimiter
    ) {}

    public function checkLimit(string $identifier): void
    {
        $this->rateLimiter->checkLimit($identifier);
    }
}
```

## Monitoring Integration

### 1. Custom Metrics Collection

```php
use Sterk\GraphQlPerformance\Model\Performance\MetricsCollector;

class CustomMetricsCollector
{
    public function __construct(
        private readonly MetricsCollector $metricsCollector
    ) {}

    public function collectMetrics(): array
    {
        return [
            'custom_metric' => $this->metricsCollector->getMetrics(),
            'custom_stats' => $this->getCustomStats()
        ];
    }
}
```

### 2. Performance Logging

```php
use Sterk\GraphQlPerformance\Logger\Logger;

class CustomPerformanceLogger
{
    public function __construct(
        private readonly Logger $logger
    ) {}

    public function logPerformance(array $metrics): void
    {
        $this->logger->info('Custom performance metrics', $metrics);
    }
}
```

## Best Practices

### 1. Cache Management
- Use specific cache tags for better invalidation
- Implement proper cache warming strategies
- Handle cache invalidation events

### 2. Performance Optimization
- Use batch loading for related entities
- Implement proper indexing strategies
- Monitor and optimize query complexity

### 3. Security Considerations
- Implement proper input validation
- Use rate limiting where appropriate
- Handle sensitive data properly

### 4. Error Handling
- Implement proper error logging
- Handle edge cases gracefully
- Provide meaningful error messages

## Troubleshooting

### Common Issues

1. Cache Issues
```php
// Check cache configuration
$cacheConfig = $this->config->getCacheConfig();
if (!$cacheConfig['enabled']) {
    // Handle disabled cache
}
```

2. Performance Issues
```php
// Monitor query execution time
$this->queryTimer->start('custom_operation');
try {
    // Your operation
} finally {
    $duration = $this->queryTimer->stop('custom_operation');
    if ($duration > 1000) {
        // Log slow operation
    }
}
```

3. Memory Issues
```php
// Monitor memory usage
$memoryUsage = memory_get_usage(true);
if ($memoryUsage > $this->config->getMemoryLimit()) {
    // Handle high memory usage
}
```
