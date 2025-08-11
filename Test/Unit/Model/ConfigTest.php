<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Sterk\GraphQlPerformance\Model\Config;

class ConfigTest extends TestCase
{
    /**
     * @var ScopeConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $scopeConfig;

    /**
     * @var Config
     */
    private $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    /**
     * @dataProvider getCacheConfigDataProvider
     */
    public function testGetCacheConfig(string $field, string $scope, ?string $scopeCode, mixed $expectedValue): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                'graphql_performance/cache/' . $field,
                $scope,
                $scopeCode
            )
            ->willReturn($expectedValue);

        $result = $this->config->getCacheConfig($field, $scope, $scopeCode);
        $this->assertEquals($expectedValue, $result);
    }

    /**
     * @return array
     */
    public function getCacheConfigDataProvider(): array
    {
        return [
            'default_lifetime' => [
                'field' => 'lifetime',
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeCode' => null,
                'expectedValue' => '3600'
            ],
            'redis_enabled' => [
                'field' => 'enable_redis_cache',
                'scope' => ScopeInterface::SCOPE_STORE,
                'scopeCode' => '1',
                'expectedValue' => '1'
            ],
            'custom_scope' => [
                'field' => 'cache_prefix',
                'scope' => ScopeInterface::SCOPE_WEBSITE,
                'scopeCode' => '2',
                'expectedValue' => 'custom_prefix'
            ]
        ];
    }

    public function testGetCacheLifetime(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                'graphql_performance/cache/lifetime',
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn('7200');

        $result = $this->config->getCacheLifetime();
        $this->assertEquals(7200, $result);
    }

    public function testGetCacheLifetimeDefaultValue(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                'graphql_performance/cache/lifetime',
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn(null);

        $result = $this->config->getCacheLifetime();
        $this->assertEquals(3600, $result);
    }

    public function testIsFullPageCacheEnabled(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                'graphql_performance/cache/enable_full_page_cache',
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn('1');

        $result = $this->config->isFullPageCacheEnabled();
        $this->assertTrue($result);
    }

    public function testGetMaxQueryComplexity(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                'graphql_performance/query/max_complexity',
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn('500');

        $result = $this->config->getMaxQueryComplexity();
        $this->assertEquals(500, $result);
    }

    public function testGetMaxQueryComplexityDefaultValue(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                'graphql_performance/query/max_complexity',
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn(null);

        $result = $this->config->getMaxQueryComplexity();
        $this->assertEquals(300, $result);
    }

    public function testGetBatchSize(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                'graphql_performance/query/batch_size',
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn('200');

        $result = $this->config->getBatchSize();
        $this->assertEquals(200, $result);
    }

    public function testGetConnectionPoolConfig(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                'graphql_performance/connection_pool/max_connections',
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn('100');

        $result = $this->config->getConnectionPoolConfig('max_connections');
        $this->assertEquals('100', $result);
    }
}
