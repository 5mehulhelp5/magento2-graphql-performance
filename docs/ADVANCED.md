# Advanced Configuration and Usage

This document provides detailed information about advanced features and configurations of the Sterk_GraphQlPerformance module.

## Cache Strategies

### Cache Tags Strategy

The module supports different cache tagging strategies:

1. **Specific** (Default):
   ```xml
   <cache_tags_strategy>specific</cache_tags_strategy>
   ```
   - Creates precise cache tags for each entity
   - Better invalidation granularity
   - Higher memory usage for tag storage

2. **Grouped**:
   ```xml
   <cache_tags_strategy>grouped</cache_tags_strategy>
   ```
   - Groups similar entities under common tags
   - More efficient tag storage
   - Less precise invalidation

3. **Minimal**:
   ```xml
   <cache_tags_strategy>minimal</cache_tags_strategy>
   ```
   - Uses minimal set of tags
   - Most efficient storage
   - Broader invalidation scope

### Cache Warming

Configure automatic cache warming:

```xml
<cache_warming_enabled>1</cache_warming_enabled>
<cache_warming_patterns>
    <category_list>query CategoryList { categories { items { id name } } }</category_list>
    <product_list>query ProductList { products { items { id sku } } }</product_list>
</cache_warming_patterns>
```

## Query Complexity Management

### Custom Complexity Rules

Define custom complexity rules for specific fields:

```php
class CustomComplexityCalculator extends ComplexityCalculator
{
    protected function getFieldComplexity($type, $field): int
    {
        if ($type === 'Product' && $field === 'related_products') {
            return 5; // Higher complexity for related products
        }
        return parent::getFieldComplexity($type, $field);
    }
}
```

### Query Depth Analysis

Configure depth limits based on operation type:

```xml
<query>
    <max_depth>
        <query>20</query>
        <mutation>10</mutation>
        <subscription>5</subscription>
    </max_depth>
</query>
```

## Connection Pool Optimization

### Connection Strategies

1. **Static Pool**:
   ```xml
   <connection_pool>
       <strategy>static</strategy>
       <max_connections>50</max_connections>
       <min_connections>5</min_connections>
   </connection_pool>
   ```

2. **Dynamic Pool**:
   ```xml
   <connection_pool>
       <strategy>dynamic</strategy>
       <initial_size>10</initial_size>
       <growth_factor>1.5</growth_factor>
       <max_size>100</max_size>
   </connection_pool>
   ```

### Health Checking

Configure connection health monitoring:

```xml
<connection_pool>
    <health_check>
        <enabled>1</enabled>
        <interval>60</interval>
        <timeout>5</timeout>
        <retry_attempts>3</retry_attempts>
    </health_check>
</connection_pool>
```

## Performance Monitoring

### Metrics Collection

Enable detailed metrics:

```xml
<monitoring>
    <metrics>
        <resolution_time>1</resolution_time>
        <memory_usage>1</memory_usage>
        <cache_statistics>1</cache_statistics>
        <query_depth>1</query_depth>
    </metrics>
</monitoring>
```

### Prometheus Integration

Configure Prometheus metrics export:

```xml
<monitoring>
    <metrics_provider>prometheus</metrics_provider>
    <metrics_endpoint>/metrics</metrics_endpoint>
    <metrics_prefix>magento_graphql_</metrics_prefix>
</monitoring>
```

Example metrics:
```
# HELP magento_graphql_query_duration_seconds Query execution duration
# TYPE magento_graphql_query_duration_seconds histogram
magento_graphql_query_duration_seconds_bucket{operation="ProductList",le="0.1"} 100
magento_graphql_query_duration_seconds_bucket{operation="ProductList",le="0.5"} 150
magento_graphql_query_duration_seconds_bucket{operation="ProductList",le="1.0"} 175
```

## Field Resolver Optimization

### Batch Loading Configuration

Configure batch loading behavior:

```xml
<field_resolvers>
    <product>
        <batch_attributes>
            <enabled>1</enabled>
            <batch_size>100</batch_size>
            <preload_attributes>name,price,status</preload_attributes>
        </batch_attributes>
    </product>
</field_resolvers>
```

### Custom Field Resolution

Implement custom field resolvers:

```php
class CustomFieldResolver implements BatchResolverInterface
{
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = [],
        array $args = []
    ): array {
        // Implementation
    }
}
```

Register in `di.xml`:
```xml
<type name="Vendor\Module\Model\Resolver\Entity">
    <arguments>
        <argument name="fieldResolvers" xsi:type="array">
            <item name="custom_field" xsi:type="object">CustomFieldResolver</item>
        </argument>
    </arguments>
</type>
```

## Security Features

### Query Whitelisting

Enable and configure query whitelisting:

```xml
<security>
    <query_whitelist>
        <enabled>1</enabled>
        <auto_update>1</auto_update>
        <whitelist_file>var/graphql/whitelist.json</whitelist_file>
    </query_whitelist>
</security>
```

Example whitelist:
```json
{
    "queries": [
        {
            "name": "ProductList",
            "hash": "abc123",
            "query": "query ProductList { products { items { id name } } }"
        }
    ]
}
```

### Rate Limiting

Configure rate limiting:

```xml
<security>
    <rate_limiting>
        <enabled>1</enabled>
        <strategies>
            <ip>
                <enabled>1</enabled>
                <limit>1000</limit>
                <window>3600</window>
            </ip>
            <token>
                <enabled>1</enabled>
                <limit>5000</limit>
                <window>3600</window>
            </token>
        </strategies>
    </rate_limiting>
</security>
```

## Response Optimization

### Compression Configuration

Configure response compression:

```xml
<response>
    <compression>
        <enabled>1</enabled>
        <methods>
            <gzip>
                <enabled>1</enabled>
                <level>6</level>
            </gzip>
            <br>
                <enabled>1</enabled>
                <quality>4</quality>
            </br>
        </methods>
        <min_size>1024</min_size>
    </compression>
</response>
```

### Caching Headers

Configure response caching headers:

```xml
<response>
    <caching_headers>
        <enabled>1</enabled>
        <ttl>3600</ttl>
        <vary>
            <store>1</store>
            <currency>1</currency>
            <customer_group>1</customer_group>
        </vary>
        <cache_control>public, max-age=3600</cache_control>
    </caching_headers>
</response>
```

## Error Handling

### Error Configuration

Configure error handling:

```xml
<error_handling>
    <show_debug_info>0</show_debug_info>
    <log_errors>1</log_errors>
    <error_log_file>var/log/graphql_errors.log</error_log_file>
    <sentry>
        <enabled>0</enabled>
        <dsn>https://your-sentry-dsn</dsn>
        <environment>production</environment>
    </sentry>
</error_handling>
```

### Custom Error Formatting

Implement custom error formatter:

```php
class CustomErrorFormatter implements ErrorFormatterInterface
{
    public function formatError(Error $error): array
    {
        return [
            'message' => $error->getMessage(),
            'category' => $this->getErrorCategory($error),
            'locations' => $error->getLocations(),
            'path' => $error->getPath(),
        ];
    }
}
```

## Performance Tips

1. **Optimal Batch Sizes**:
   - Product attributes: 100-200
   - Category children: 50-100
   - Cart items: 20-50

2. **Cache Lifetime Guidelines**:
   - Product data: 3600s (1 hour)
   - Category tree: 7200s (2 hours)
   - Cart data: 300s (5 minutes)
   - Customer data: 1800s (30 minutes)

3. **Memory Management**:
   - Enable lazy loading for large collections
   - Use cursor-based pagination
   - Implement proper garbage collection

4. **Query Optimization**:
   - Use field selection to minimize data loading
   - Implement proper indexing
   - Monitor and optimize slow queries
