# GraphQL Performance Module Configuration Guide

## System Configuration

### Admin Panel Settings

Navigate to `Stores > Configuration > Sterk > GraphQL Performance`

#### 1. Cache Settings

```xml
<group id="cache_settings" translate="label" type="text" sortOrder="10" showInDefault="1" showInWebsite="1" showInStore="1">
    <label>Cache Settings</label>
    <field id="enable_caching" translate="label" type="select" sortOrder="10" showInDefault="1" showInWebsite="1" showInStore="1">
        <label>Enable Caching</label>
        <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
    </field>
    <field id="cache_lifetime" translate="label" type="text" sortOrder="20" showInDefault="1" showInWebsite="1" showInStore="1">
        <label>Default Cache Lifetime (seconds)</label>
        <validate>validate-number</validate>
    </field>
    <field id="enable_cache_warming" translate="label" type="select" sortOrder="30" showInDefault="1" showInWebsite="1" showInStore="1">
        <label>Enable Cache Warming</label>
        <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
    </field>
    <field id="cache_warming_schedule" translate="label" type="text" sortOrder="40" showInDefault="1" showInWebsite="1" showInStore="1">
        <label>Cache Warming Schedule (cron expression)</label>
        <comment>Default: 0 */6 * * * (every 6 hours)</comment>
    </field>
</group>
```

#### 2. Performance Limits

```xml
<group id="performance_limits" translate="label" type="text" sortOrder="20" showInDefault="1" showInWebsite="1" showInStore="1">
    <label>Performance Limits</label>
    <field id="max_query_complexity" translate="label" type="text" sortOrder="10" showInDefault="1" showInWebsite="1" showInStore="1">
        <label>Maximum Query Complexity</label>
        <validate>validate-number</validate>
        <comment>Default: 1000</comment>
    </field>
    <field id="max_query_depth" translate="label" type="text" sortOrder="20" showInDefault="1" showInWebsite="1" showInStore="1">
        <label>Maximum Query Depth</label>
        <validate>validate-number</validate>
        <comment>Default: 10</comment>
    </field>
    <field id="batch_size" translate="label" type="text" sortOrder="30" showInDefault="1" showInWebsite="1" showInStore="1">
        <label>Default Batch Size</label>
        <validate>validate-number</validate>
        <comment>Default: 50</comment>
    </field>
</group>
```

#### 3. Database Settings

```xml
<group id="database_settings" translate="label" type="text" sortOrder="30" showInDefault="1" showInWebsite="1" showInStore="1">
    <label>Database Settings</label>
    <field id="min_connections" translate="label" type="text" sortOrder="10" showInDefault="1" showInWebsite="1" showInStore="1">
        <label>Minimum Connections</label>
        <validate>validate-number</validate>
        <comment>Default: 5</comment>
    </field>
    <field id="max_connections" translate="label" type="text" sortOrder="20" showInDefault="1" showInWebsite="1" showInStore="1">
        <label>Maximum Connections</label>
        <validate>validate-number</validate>
        <comment>Default: 50</comment>
    </field>
    <field id="connection_timeout" translate="label" type="text" sortOrder="30" showInDefault="1" showInWebsite="1" showInStore="1">
        <label>Connection Timeout (seconds)</label>
        <validate>validate-number</validate>
        <comment>Default: 5</comment>
    </field>
</group>
```

## Cache Configuration

### 1. Entity-Specific Cache Settings

```xml
<type name="Sterk\GraphQlPerformance\Model\Cache\ResolverCache">
    <arguments>
        <argument name="entityConfig" xsi:type="array">
            <item name="product" xsi:type="array">
                <item name="lifetime" xsi:type="number">3600</item>
                <item name="tags" xsi:type="array">
                    <item name="0" xsi:type="string">catalog_product</item>
                </item>
            </item>
            <item name="category" xsi:type="array">
                <item name="lifetime" xsi:type="number">7200</item>
                <item name="tags" xsi:type="array">
                    <item name="0" xsi:type="string">catalog_category</item>
                </item>
            </item>
            <item name="cms" xsi:type="array">
                <item name="lifetime" xsi:type="number">86400</item>
                <item name="tags" xsi:type="array">
                    <item name="0" xsi:type="string">cms_page</item>
                    <item name="1" xsi:type="string">cms_block</item>
                </item>
            </item>
        </argument>
    </arguments>
</type>
```

### 2. Cache Warming Patterns

```xml
<type name="Sterk\GraphQlPerformance\Model\Cache\CacheWarmer">
    <arguments>
        <argument name="defaultQueries" xsi:type="array">
            <item name="categories" xsi:type="string">
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
            </item>
            <!-- Add more default queries -->
        </argument>
    </arguments>
</type>
```

## Performance Monitoring

### 1. Metrics Collection

```xml
<type name="Sterk\GraphQlPerformance\Model\Performance\MetricsCollector">
    <arguments>
        <argument name="metrics" xsi:type="array">
            <item name="execution_time" xsi:type="boolean">true</item>
            <item name="memory_usage" xsi:type="boolean">true</item>
            <item name="cache_hits" xsi:type="boolean">true</item>
            <item name="database_queries" xsi:type="boolean">true</item>
            <item name="query_complexity" xsi:type="boolean">true</item>
        </argument>
    </arguments>
</type>
```

### 2. Performance Reporting

```xml
<type name="Sterk\GraphQlPerformance\Model\Performance\ReportGenerator">
    <arguments>
        <argument name="reportConfig" xsi:type="array">
            <item name="enabled" xsi:type="boolean">true</item>
            <item name="format" xsi:type="string">json</item>
            <item name="retention_days" xsi:type="number">30</item>
            <item name="metrics" xsi:type="array">
                <item name="slow_queries" xsi:type="boolean">true</item>
                <item name="cache_efficiency" xsi:type="boolean">true</item>
                <item name="resource_usage" xsi:type="boolean">true</item>
            </item>
        </argument>
    </arguments>
</type>
```

## DataLoader Configuration

### 1. Batch Sizes

```xml
<type name="Sterk\GraphQlPerformance\Model\DataLoader\BatchDataLoader">
    <arguments>
        <argument name="batchSizes" xsi:type="array">
            <item name="product" xsi:type="number">50</item>
            <item name="category" xsi:type="number">20</item>
            <item name="customer" xsi:type="number">30</item>
            <item name="order" xsi:type="number">25</item>
        </argument>
    </arguments>
</type>
```

### 2. Cache Lifetimes

```xml
<type name="Sterk\GraphQlPerformance\Model\DataLoader\FrequentDataLoader">
    <arguments>
        <argument name="cacheLifetimes" xsi:type="array">
            <item name="product" xsi:type="number">3600</item>
            <item name="category" xsi:type="number">7200</item>
            <item name="cms" xsi:type="number">86400</item>
        </argument>
    </arguments>
</type>
```

## Query Complexity Rules

### 1. Field Complexity

```xml
<type name="Sterk\GraphQlPerformance\Model\QueryComplexity\ComplexityCalculator">
    <arguments>
        <argument name="fieldComplexity" xsi:type="array">
            <item name="products" xsi:type="number">10</item>
            <item name="categories" xsi:type="number">5</item>
            <item name="customer" xsi:type="number">3</item>
            <item name="orders" xsi:type="number">8</item>
            <item name="cart" xsi:type="number">4</item>
        </argument>
    </arguments>
</type>
```

### 2. Depth Limits

```xml
<type name="Sterk\GraphQlPerformance\Model\QueryComplexity\ComplexityValidator">
    <arguments>
        <argument name="depthLimits" xsi:type="array">
            <item name="default" xsi:type="number">10</item>
            <item name="categories" xsi:type="number">5</item>
            <item name="products" xsi:type="number">8</item>
        </argument>
    </arguments>
</type>
```

## Logging Configuration

### 1. Log Settings

```xml
<type name="Sterk\GraphQlPerformance\Model\Logger\Handler">
    <arguments>
        <argument name="logConfig" xsi:type="array">
            <item name="enabled" xsi:type="boolean">true</item>
            <item name="file" xsi:type="string">var/log/graphql_performance.log</item>
            <item name="max_files" xsi:type="number">30</item>
            <item name="level" xsi:type="string">INFO</item>
        </argument>
    </arguments>
</type>
```

### 2. Error Tracking

```xml
<type name="Sterk\GraphQlPerformance\Model\Logger\ErrorHandler">
    <arguments>
        <argument name="errorConfig" xsi:type="array">
            <item name="track_slow_queries" xsi:type="boolean">true</item>
            <item name="slow_query_threshold" xsi:type="number">5000</item>
            <item name="track_memory_usage" xsi:type="boolean">true</item>
            <item name="memory_threshold" xsi:type="number">256</item>
        </argument>
    </arguments>
</type>
```
