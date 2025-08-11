<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Security;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Sterk\GraphQlPerformance\Model\Config;
use Sterk\GraphQlPerformance\Model\Security\SecurityPattern;

class RequestValidator
{
    private const MAX_QUERY_SIZE = 8000;

    public function __construct(
        private readonly Config $config,
        private readonly RateLimiter $rateLimiter
    ) {}

    /**
     * Validate GraphQL request
     *
     * @param RequestInterface $request
     * @param string $query
     * @param array $variables
     * @throws LocalizedException
     */
    public function validate(RequestInterface $request, string $query, array $variables = []): void
    {
        $this->validateQuerySize($query);
        $this->validateForbiddenPatterns($query);
        $this->validateVariables($variables);
        $this->validateRateLimit($request);
        $this->validateAuthentication($request);
    }

    /**
     * Validate query size
     *
     * @param string $query
     * @throws LocalizedException
     */
    private function validateQuerySize(string $query): void
    {
        if (strlen($query) > self::MAX_QUERY_SIZE) {
            throw new LocalizedException(
                __('Query size exceeds maximum allowed size of %1 characters', self::MAX_QUERY_SIZE)
            );
        }
    }

    /**
     * Validate forbidden patterns
     *
     * @param string $query
     * @throws LocalizedException
     */
    private function validateForbiddenPatterns(string $query): void
    {
        $normalizedQuery = strtolower(preg_replace('/\s+/', ' ', $query));

        foreach (SecurityPattern::cases() as $pattern) {
            if (preg_match('/' . $pattern->value . '/i', $normalizedQuery)) {
                throw new LocalizedException(
                    __($pattern->getDescription())
                );
            }
        }
    }

    /**
     * Validate variables
     *
     * @param array $variables
     * @throws LocalizedException
     */
    private function validateVariables(array $variables): void
    {
        array_walk_recursive($variables, function ($value, $key) {
            // Validate variable names
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                throw new LocalizedException(
                    __('Invalid variable name: %1', $key)
                );
            }

            // Validate string values
            if (is_string($value)) {
                $this->validateStringValue($value, $key);
            }
        });
    }

    /**
     * Validate string value
     *
     * @param string $value
     * @param string $key
     * @throws LocalizedException
     */
    private function validateStringValue(string $value, string $key): void
    {
        // Check for potential SQL injection
        if (preg_match('/(union|select|insert|update|delete|drop|alter|create|rename)\s/i', $value)) {
            throw new LocalizedException(
                __('Invalid value for variable: %1', $key)
            );
        }

        // Check for potential XSS
        if (preg_match('/<script|javascript:|data:|vbscript:|onload=|onerror=/i', $value)) {
            throw new LocalizedException(
                __('Invalid value for variable: %1', $key)
            );
        }
    }

    /**
     * Validate rate limit
     *
     * @param RequestInterface $request
     * @throws LocalizedException
     */
    private function validateRateLimit(RequestInterface $request): void
    {
        if ($this->rateLimiter->shouldLimit($request)) {
            throw new LocalizedException(
                __('Rate limit exceeded. Please try again later.')
            );
        }
    }

    /**
     * Validate authentication
     *
     * @param RequestInterface $request
     * @throws LocalizedException
     */
    private function validateAuthentication(RequestInterface $request): void
    {
        $token = $request->getHeader('Authorization');

        if ($this->config->isAuthRequired() && !$token) {
            throw new LocalizedException(
                __('Authentication required')
            );
        }
    }
}
