<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Security;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Sterk\GraphQlPerformance\Model\Config;
use Sterk\GraphQlPerformance\Model\Security\SecurityPattern;

/**
 * Service class for validating GraphQL requests
 *
 * This class performs various security validations on GraphQL requests,
 * including query size limits, forbidden patterns, variable validation,
 * rate limiting, and authentication checks. It helps protect against
 * malicious queries and ensures proper API usage.
 */
class RequestValidator
{
    private const MAX_QUERY_SIZE = 8000;

    /**
     * @param Config $config Configuration service
     * @param RateLimiter $rateLimiter Rate limiting service
     */
    public function __construct(
        private readonly Config $config,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    /**
     * Validate GraphQL request
     *
     * @param  RequestInterface $request
     * @param  string           $query
     * @param  array            $variables
     * @throws LocalizedException
     */
    public function validate(RequestInterface $request, string $query, array $variables = []): void
    {
        // Skip validation for introspection queries
        if ($this->isIntrospectionQuery($query)) {
            return;
        }

        $this->validateQuerySize($query);
        $this->validateForbiddenPatterns($query);
        $this->validateVariables($variables);
        $this->validateRateLimit($request);
        $this->validateAuthentication($request);
    }

    /**
     * Validate query size
     *
     * @param  string $query
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
     * @param  string $query
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
     * @param  array $variables
     * @throws LocalizedException
     */
    private function validateVariables(array $variables): void
    {
        array_walk_recursive(
            $variables,
            function ($value, $key) {
                // Only validate string keys (skip numeric array indices)
                if (is_string($key)) {
                    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                        throw new LocalizedException(
                            __('Invalid variable name: %1', $key)
                        );
                    }
                }

                // Validate string values
                if (is_string($value)) {
                    $this->validateStringValue($value, $key);
                }
            }
        );
    }

    /**
     * Validate string value
     *
     * @param  string $value
     * @param  string $key
     * @throws LocalizedException
     */
    private function validateStringValue(string $value, string $key): void
    {
        // Skip validation for known safe values
        if ($this->isSafeValue($key, $value)) {
            return;
        }

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
     * Check if the value is known to be safe
     *
     * @param string $key Variable name
     * @param string $value Variable value
     * @return bool
     */
    private function isSafeValue(string $key, string $value): bool
    {
        // Safe keys that can contain special characters
        $safeKeys = [
            // Standard GraphQL fields
            'name',
            'match',
            'eq',
            'like',
            'url',
            'url_key',
            'url_path',
            'identifier',
            'search',
            
            // Edip Saat specific fields
            'es_swiss_made',
            'es_webe_ozel',
            'es_outlet_urun',
            'es_teklife_acik',
            'es_kasa_capi',
            'es_saat_mekanizma',
            'es_kasa_cinsi',
            'es_kordon_tipi',
            'manufacturer',
            'category_uid',
            'store',
            'content-currency',
            'preview-version',
            'x-magento-cache-id',
            'x-forwarded-for',
            'x-recaptcha',
            'x-api-key'
        ];

        if (in_array($key, $safeKeys)) {
            return true;
        }

        // Safe patterns for values
        $safePatterns = [
            '/^[a-zA-ZğüşıöçĞÜŞİÖÇ0-9\s\-_\/\.]+$/u', // Turkish characters, numbers, spaces, hyphens, underscores, slashes, dots
            '/^[0-9]+$/', // Numbers only
            '/^[A-Z0-9]{3,}$/', // Category UIDs (e.g., NTc5)
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', // UUIDs
            '/^(true|false|1|0)$/', // Boolean values
            '/^[A-Z]{2}$/', // Two-letter country/store codes
            '/^[A-Z]{2}-[A-Z]{2}$/' // Locale codes (e.g., TR-tr)
        ];

        foreach ($safePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate rate limit
     *
     * @param  RequestInterface $request
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
     * @param  RequestInterface $request
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

    /**
     * Check if query is an introspection query
     *
     * @param  string $query
     * @return bool
     */
    private function isIntrospectionQuery(string $query): bool
    {
        $normalizedQuery = strtolower(preg_replace('/\s+/', ' ', $query));

        // Skip validation for introspection queries
        if (str_contains($normalizedQuery, '__schema') || str_contains($normalizedQuery, '__type')) {
            return true;
        }

        // Skip validation for standard Magento queries
        $standardQueries = [
            'products(',
            'categories(',
            'storeconfig',
            'brandcategories(',
            'cmsblocks(',
            'cmspage('
        ];

        foreach ($standardQueries as $standardQuery) {
            if (str_contains($normalizedQuery, $standardQuery)) {
                return true;
            }
        }

        return false;
    }
}
