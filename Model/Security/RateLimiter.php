<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Security;

use Magento\Framework\App\Cache\Type\Config as CacheConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Sterk\GraphQlPerformance\Model\Config;

class RateLimiter
{
    private const CACHE_KEY_PREFIX = 'graphql_rate_limit_';
    private const WINDOW_SIZE = 3600; // 1 hour in seconds

    public function __construct(
        private readonly Config $config,
        private readonly CacheConfig $cache,
        private readonly RequestInterface $request
    ) {}

    /**
     * Check if request should be rate limited
     *
     * @param string $identifier
     * @return bool
     * @throws LocalizedException
     */
    public function shouldLimit(string $identifier = ''): bool
    {
        if (!$this->isRateLimitingEnabled()) {
            return false;
        }

        $key = $this->generateCacheKey($identifier);
        $currentCount = (int)$this->cache->load($key);

        if ($currentCount >= $this->getMaxRequests()) {
            throw new LocalizedException(
                __('Rate limit exceeded. Please try again later.')
            );
        }

        $this->incrementCounter($key, $currentCount);
        return false;
    }

    /**
     * Generate cache key for rate limiting
     *
     * @param string $identifier
     * @return string
     */
    private function generateCacheKey(string $identifier = ''): string
    {
        if (empty($identifier)) {
            $identifier = $this->getClientIdentifier();
        }

        return self::CACHE_KEY_PREFIX . hash('sha256', $identifier . '_' . floor(time() / self::WINDOW_SIZE));
    }

    /**
     * Get client identifier based on configuration
     *
     * @return string
     */
    private function getClientIdentifier(): string
    {
        $identifiers = [];

        if ($this->config->getQueryConfig('rate_limiting/by_ip')) {
            $identifiers[] = $this->request->getClientIp();
        }

        if ($this->config->getQueryConfig('rate_limiting/by_token')) {
            $token = $this->request->getHeader('Authorization');
            if ($token) {
                $identifiers[] = $token;
            }
        }

        // Add user agent as additional entropy
        $identifiers[] = $this->request->getHeader('User-Agent');

        return implode('_', array_filter($identifiers));
    }

    /**
     * Increment request counter
     *
     * @param string $key
     * @param int $currentCount
     * @return void
     */
    private function incrementCounter(string $key, int $currentCount): void
    {
        $this->cache->save(
            (string)($currentCount + 1),
            $key,
            [],
            self::WINDOW_SIZE
        );
    }

    /**
     * Check if rate limiting is enabled
     *
     * @return bool
     */
    private function isRateLimitingEnabled(): bool
    {
        return (bool)$this->config->getQueryConfig('rate_limiting/enabled');
    }

    /**
     * Get maximum allowed requests per window
     *
     * @return int
     */
    private function getMaxRequests(): int
    {
        return (int)$this->config->getQueryConfig('rate_limiting/max_requests') ?: 1000;
    }

    /**
     * Reset rate limit for identifier
     *
     * @param string $identifier
     * @return bool
     */
    public function resetLimit(string $identifier = ''): bool
    {
        $key = $this->generateCacheKey($identifier);
        return $this->cache->remove($key);
    }

    /**
     * Get current request count
     *
     * @param string $identifier
     * @return int
     */
    public function getCurrentCount(string $identifier = ''): int
    {
        $key = $this->generateCacheKey($identifier);
        return (int)$this->cache->load($key);
    }

    /**
     * Get remaining requests allowed
     *
     * @param string $identifier
     * @return int
     */
    public function getRemainingRequests(string $identifier = ''): int
    {
        $maxRequests = $this->getMaxRequests();
        $currentCount = $this->getCurrentCount($identifier);
        return max(0, $maxRequests - $currentCount);
    }

    /**
     * Get time until reset
     *
     * @return int
     */
    public function getTimeUntilReset(): int
    {
        return self::WINDOW_SIZE - (time() % self::WINDOW_SIZE);
    }
}

