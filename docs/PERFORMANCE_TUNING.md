# Performance Tuning Guide

## Overview
This guide provides detailed information about optimizing the GraphQL Performance module for maximum efficiency.

## Cache Configuration

### Cache Lifetime Settings
```xml
<graphql_performance>
    <cache>
        <lifetime>3600</lifetime>
        <!-- Increase for stable data -->
        <product_lifetime>7200</product_lifetime>
        <category_lifetime>86400</category_lifetime>
    </cache>
</graphql_performance>
```

### Cache Storage
1. **Redis (Recommended)**
   ```xml
   <cache>
       <enable_redis_cache>1</enable_redis_cache>
       <redis_connection>cache</redis_connection>
   </cache>
   ```

2. **File System**
   ```xml
   <cache>
       <enable_file_cache>1</enable_file_cache>
       <file_cache_dir>var/graphql-cache</file_cache_dir>
   </cache>
   ```

## Connection Pool Settings

### Pool Size
```xml
<connection_pool>
    <max_connections>50</max_connections>
    <min_connections>5</min_connections>
    <idle_timeout>300</idle_timeout>
</connection_pool>
```

Recommendations:
- For high traffic: max_connections = 100, min_connections = 10
- For medium traffic: max_connections = 50, min_connections = 5
- For low traffic: max_connections = 20, min_connections = 2

### Connection Timeouts
```xml
<connection_pool>
    <connection_timeout>5</connection_timeout>
    <retry_limit>3</retry_limit>
    <retry_delay>1000</retry_delay>
</connection_pool>
```

## Query Optimization

### Complexity Limits
```xml
<query>
    <max_complexity>300</max_complexity>
    <max_depth>20</max_depth>
    <batch_size>100</batch_size>
</query>
```

### Rate Limiting
```xml
<query>
    <rate_limiting>
        <enabled>1</enabled>
        <max_requests>1000</max_requests>
        <time_window>3600</time_window>
    </rate_limiting>
</query>
```

## Memory Management

### Batch Processing
```xml
<field_resolvers>
    <product>
        <batch_size>100</batch_size>
        <lazy_load_attributes>1</lazy_load_attributes>
    </product>
</field_resolvers>
```

### Memory Limits
```xml
<monitoring>
    <memory_limit>256M</memory_limit>
    <alert_threshold>80</alert_threshold>
</monitoring>
```

## Cache Warming

### Configuration
```xml
<cache>
    <cache_warming_enabled>1</cache_warming_enabled>
    <cache_warming_patterns>
        <category_list>
            query CategoryList {
                categories {
                    items {
                        id
                        name
                        url_key
                    }
                }
            }
        </category_list>
    </cache_warming_patterns>
</cache>
```

### Scheduling
```xml
<crontab>
    <jobs>
        <graphql_cache_warm>
            <schedule>*/30 * * * *</schedule>
        </graphql_cache_warm>
    </jobs>
</crontab>
```

## Monitoring and Alerts

### Performance Metrics
```xml
<monitoring>
    <enable_query_logging>1</enable_query_logging>
    <slow_query_threshold>1000</slow_query_threshold>
    <metrics_enabled>1</metrics_enabled>
</monitoring>
```

### Alert Thresholds
```xml
<monitoring>
    <alert_thresholds>
        <response_time>5000</response_time>
        <error_rate>0.05</error_rate>
        <memory_usage>256</memory_usage>
    </alert_thresholds>
</monitoring>
```

## Best Practices

### 1. Cache Strategy
- Use specific cache tags for better invalidation
- Implement cache warming for frequently accessed data
- Configure appropriate cache lifetimes based on data volatility

### 2. Query Optimization
- Use field selection to minimize data loading
- Implement pagination for large result sets
- Leverage batch loading for related data

### 3. Resource Management
- Monitor and adjust connection pool settings
- Implement proper error handling and retries
- Use batch processing for bulk operations

### 4. Monitoring
- Enable performance logging in production
- Set up alerts for performance thresholds
- Regularly review performance metrics

## Troubleshooting

### Common Issues

1. **High Memory Usage**
   - Reduce batch sizes
   - Enable lazy loading
   - Increase memory_limit

2. **Slow Queries**
   - Check query complexity
   - Review cache hit rates
   - Optimize database indexes

3. **Cache Issues**
   - Verify cache configuration
   - Check cache tag management
   - Monitor cache invalidation

## Performance Testing

### Load Testing
```bash
# Using k6 for load testing
k6 run performance-test.js --vus 10 --duration 30s
```

### Benchmark Script
```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export default function() {
    const query = `
        query {
            products(pageSize: 20) {
                items {
                    id
                    name
                    sku
                }
            }
        }
    `;

    const res = http.post('http://your-magento-url/graphql', JSON.stringify({
        query: query
    }), {
        headers: { 'Content-Type': 'application/json' },
    });

    check(res, {
        'is status 200': (r) => r.status === 200,
        'response time < 1000ms': (r) => r.timings.duration < 1000
    });

    sleep(1);
}
```
