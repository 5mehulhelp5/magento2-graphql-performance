<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Sterk\GraphQlPerformance\Model\Config\ConfigPath;

class Config
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    /**
     * Get cache configuration
     *
     * @param string $field
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return mixed
     */
    public function getCacheConfig(
        string $field,
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): mixed {
        return $this->scopeConfig->getValue(
            ConfigPath::CACHE->getPath($field),
            $scope,
            $scopeCode
        );
    }

    /**
     * Get query configuration
     *
     * @param string $field
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return mixed
     */
    public function getQueryConfig(
        string $field,
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): mixed {
        return $this->scopeConfig->getValue(
            ConfigPath::QUERY->getPath($field),
            $scope,
            $scopeCode
        );
    }

    /**
     * Get connection pool configuration
     *
     * @param string $field
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return mixed
     */
    public function getConnectionPoolConfig(
        string $field,
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): mixed {
        return $this->scopeConfig->getValue(
            ConfigPath::CONNECTION_POOL->getPath($field),
            $scope,
            $scopeCode
        );
    }

    /**
     * Get monitoring configuration
     *
     * @param string $field
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return mixed
     */
    public function getMonitoringConfig(
        string $field,
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): mixed {
        return $this->scopeConfig->getValue(
            self::XML_PATH_MONITORING . $field,
            $scope,
            $scopeCode
        );
    }

    /**
     * Get field resolver configuration
     *
     * @param string $resolver
     * @param string $field
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return mixed
     */
    public function getFieldResolverConfig(
        string $resolver,
        string $field,
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): mixed {
        return $this->scopeConfig->getValue(
            self::XML_PATH_FIELD_RESOLVERS . $resolver . '/' . $field,
            $scope,
            $scopeCode
        );
    }

    /**
     * Get cache lifetime
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return int
     */
    public function getCacheLifetime(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): int {
        return (int)$this->getCacheConfig('lifetime', $scope, $scopeCode) ?: 3600;
    }

    /**
     * Is full page cache enabled
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return bool
     */
    public function isFullPageCacheEnabled(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): bool {
        return (bool)$this->getCacheConfig('enable_full_page_cache', $scope, $scopeCode);
    }

    /**
     * Is Redis cache enabled
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return bool
     */
    public function isRedisCacheEnabled(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): bool {
        return (bool)$this->getCacheConfig('enable_redis_cache', $scope, $scopeCode);
    }

    /**
     * Get maximum query complexity
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return int
     */
    public function getMaxQueryComplexity(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): int {
        return (int)$this->getQueryConfig('max_complexity', $scope, $scopeCode) ?: 300;
    }

    /**
     * Get maximum query depth
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return int
     */
    public function getMaxQueryDepth(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): int {
        return (int)$this->getQueryConfig('max_depth', $scope, $scopeCode) ?: 20;
    }

    /**
     * Get batch size
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return int
     */
    public function getBatchSize(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): int {
        return (int)$this->getQueryConfig('batch_size', $scope, $scopeCode) ?: 100;
    }

    /**
     * Get maximum connections
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return int
     */
    public function getMaxConnections(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): int {
        return (int)$this->getConnectionPoolConfig('max_connections', $scope, $scopeCode) ?: 50;
    }

    /**
     * Get minimum connections
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return int
     */
    public function getMinConnections(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): int {
        return (int)$this->getConnectionPoolConfig('min_connections', $scope, $scopeCode) ?: 5;
    }

    /**
     * Get idle timeout
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return int
     */
    public function getIdleTimeout(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): int {
        return (int)$this->getConnectionPoolConfig('idle_timeout', $scope, $scopeCode) ?: 300;
    }

    /**
     * Is query logging enabled
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return bool
     */
    public function isQueryLoggingEnabled(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): bool {
        return (bool)$this->getMonitoringConfig('enable_query_logging', $scope, $scopeCode);
    }

    /**
     * Get slow query threshold
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return int
     */
    public function getSlowQueryThreshold(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): int {
        return (int)$this->getMonitoringConfig('slow_query_threshold', $scope, $scopeCode) ?: 1000;
    }

    /**
     * Is performance monitoring enabled
     *
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return bool
     */
    public function isPerformanceMonitoringEnabled(
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): bool {
        return (bool)$this->getMonitoringConfig('enable_performance_monitoring', $scope, $scopeCode);
    }

    /**
     * Get resolver specific cache lifetime
     *
     * @param string $resolver
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return int
     */
    public function getResolverCacheLifetime(
        string $resolver,
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): int {
        return (int)$this->getFieldResolverConfig($resolver, 'cache_lifetime', $scope, $scopeCode)
            ?: $this->getCacheLifetime($scope, $scopeCode);
    }

    /**
     * Is batch loading enabled for resolver
     *
     * @param string $resolver
     * @param string $batchType
     * @param string $scope
     * @param mixed|null $scopeCode
     * @return bool
     */
    public function isBatchLoadingEnabled(
        string $resolver,
        string $batchType,
        string $scope = ScopeInterface::SCOPE_STORE,
        mixed $scopeCode = null
    ): bool {
        return (bool)$this->getFieldResolverConfig($resolver, 'batch_' . $batchType, $scope, $scopeCode);
    }
}
