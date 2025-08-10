# Troubleshooting Guide

This guide helps you diagnose and resolve common issues with the Sterk GraphQL Performance module.

## Table of Contents
1. [Performance Issues](#performance-issues)
2. [Caching Issues](#caching-issues)
3. [Memory Issues](#memory-issues)
4. [Security Issues](#security-issues)
5. [DataLoader Issues](#dataloader-issues)
6. [Common Errors](#common-errors)

## Performance Issues

### Slow Query Response Times

**Symptoms:**
- GraphQL queries take longer than expected to respond
- High server load during query execution
- Timeouts on complex queries

**Possible Causes:**
1. Query complexity too high
2. Inefficient batch loading
3. Cache misses
4. Database connection pool exhaustion

**Solutions:**

1. Check Query Complexity
```php
use Sterk\GraphQlPerformance\Model\QueryComplexity\ComplexityCalculator;

$complexity = $calculator->calculate($query);
if ($complexity > 300) {
    // Optimize query structure
}
```

2. Verify Batch Loading
```php
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;

$timer->start('batch_operation');
// Your batch operation
$timer->stop('batch_operation');
$metrics = $timer->getMetrics();
```

3. Monitor Cache Hit Rate
```php
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;

$cacheStats = $cache->getStats();
if ($cacheStats['hit_rate'] < 0.80) {
    // Implement cache warming
}
```

### N+1 Query Problems

**Symptoms:**
- Multiple similar database queries
- Increasing response time with data size
- High database load

**Solution:**
```php
// Instead of:
foreach ($ids as $id) {
    $item = $repository->get($id);
}

// Use batch loading:
$items = $dataLoader->loadMany($ids);
```

## Caching Issues

### Stale Data

**Symptoms:**
- Outdated information displayed
- Cache not invalidating properly
- Inconsistent data across requests

**Solutions:**

1. Check Cache Tags
```php
use Sterk\GraphQlPerformance\Model\Cache\TagManager;

$tags = $tagManager->getEntityTags($entityType, $entityId);
$cache->clean($tags);
```

2. Verify Cache Lifetime
```php
// In your configuration:
'cache_lifetime' => [
    'product' => 3600,
    'category' => 7200,
    'cms' => 86400
]
```

3. Implement Cache Warming
```php
use Sterk\GraphQlPerformance\Model\Cache\CacheWarmer;

class CustomWarmer implements CacheWarmerInterface
{
    public function warm(): void
    {
        $this->warmFrequentQueries();
        $this->warmCriticalData();
    }
}
```

### Cache Size Issues

**Symptoms:**
- High memory usage
- Slow cache operations
- Cache eviction warnings

**Solutions:**

1. Optimize Cache Keys
```php
use Sterk\GraphQlPerformance\Model\Cache\CacheKeyGenerator;

$key = $generator->generate(
    'prefix',
    ['context' => 'specific'],
    ['store' => $storeId]
);
```

2. Implement Cache Cleanup
```php
use Sterk\GraphQlPerformance\Cron\CacheCleanup;

$cleanup->execute([
    'older_than' => '1 day',
    'batch_size' => 1000
]);
```

## Memory Issues

### High Memory Usage

**Symptoms:**
- PHP memory limit errors
- Degraded performance
- Server swapping

**Solutions:**

1. Monitor Memory Usage
```php
use Sterk\GraphQlPerformance\Model\Performance\MemoryMonitor;

$monitor->start();
// Your operation
$usage = $monitor->getCurrentUsage();
$peak = $monitor->getPeakUsage();
```

2. Implement Batch Processing
```php
use Sterk\GraphQlPerformance\Model\Batch\BatchProcessor;

$processor->processBatch($items, function ($item) {
    // Process single item
}, 100);
```

## Security Issues

### Rate Limiting

**Symptoms:**
- High server load from specific IPs
- Potential DoS attempts
- Excessive API usage

**Solutions:**

1. Configure Rate Limits
```xml
<config>
    <rate_limiting>
        <enabled>1</enabled>
        <max_requests>1000</max_requests>
        <time_window>3600</time_window>
    </rate_limiting>
</config>
```

2. Implement Custom Limits
```php
use Sterk\GraphQlPerformance\Model\Security\RateLimiter;

if ($rateLimiter->shouldLimit($identifier)) {
    throw new GraphQlInputException(
        __('Rate limit exceeded')
    );
}
```

### Query Validation

**Symptoms:**
- Malicious query attempts
- High complexity queries
- Resource exhaustion

**Solutions:**

1. Validate Query Complexity
```php
use Sterk\GraphQlPerformance\Model\Security\QueryValidator;

$validator->validate($query, $variables);
```

2. Implement Query Whitelist
```php
use Sterk\GraphQlPerformance\Model\Security\QueryWhitelist;

if (!$whitelist->isAllowed($query)) {
    throw new GraphQlInputException(
        __('Query not allowed')
    );
}
```

## DataLoader Issues

### Batch Loading Failures

**Symptoms:**
- Inconsistent data loading
- Partial data returns
- Performance degradation

**Solutions:**

1. Implement Error Handling
```php
use Sterk\GraphQlPerformance\Model\DataLoader\BatchDataLoader;

class CustomLoader extends BatchDataLoader
{
    protected function batchLoad(array $ids): array
    {
        try {
            return $this->repository->getList($ids);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [];
        }
    }
}
```

2. Monitor Batch Sizes
```php
use Sterk\GraphQlPerformance\Model\Performance\BatchMonitor;

$monitor->recordBatch(
    'entity_type',
    count($ids),
    $duration
);
```

## Common Errors

### Query Complexity Exceeded

```
Error: "Query complexity of 500 exceeds maximum allowed complexity of 300"

Solution:
1. Reduce query depth
2. Remove unnecessary fields
3. Split into multiple queries
4. Adjust complexity limits in configuration
```

### Cache Backend Error

```
Error: "Cache backend not responding"

Solution:
1. Check Redis/Memcached connection
2. Verify cache configuration
3. Monitor cache backend health
4. Implement fallback caching
```

### Rate Limit Exceeded

```
Error: "Rate limit exceeded. Please try again later."

Solution:
1. Check rate limit configuration
2. Implement request throttling
3. Use token bucket algorithm
4. Add retry-after header
```

### Memory Limit Reached

```
Error: "Allowed memory size exhausted"

Solution:
1. Implement batch processing
2. Optimize memory usage
3. Adjust PHP memory limits
4. Monitor memory consumption
```

### Connection Pool Exhausted

```
Error: "No available connections in pool"

Solution:
1. Increase pool size
2. Reduce connection lifetime
3. Implement connection recycling
4. Monitor connection usage
```