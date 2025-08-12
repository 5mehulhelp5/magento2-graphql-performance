<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Service for generating cache keys for GraphQL queries
 *
 * This class handles the generation of unique cache keys for GraphQL queries
 * taking into account the query string, variables, store context, and other
 * relevant factors that could affect the query result.
 */
class CacheKeyGenerator
{
    /**
     * @param StoreManagerInterface $storeManager Store manager
     * @param RequestInterface $request Request object
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Generate cache key for GraphQL query
     *
     * @param string $query GraphQL query string
     * @param array|null $variables Query variables
     * @return string Cache key
     */
    public function generate(string $query, ?array $variables = null): string
    {
        $keyParts = [
            'graphql_query',
            md5($query),
            $variables ? md5(json_encode($variables)) : 'no_vars',
            $this->getStoreId(),
            $this->getCurrencyCode(),
            $this->getCustomerGroupId()
        ];

        return implode(':', array_filter($keyParts));
    }

    /**
     * Get current store ID
     *
     * @return string Store ID
     */
    private function getStoreId(): string
    {
        try {
            return (string)$this->storeManager->getStore()->getId();
        } catch (\Exception $e) {
            return '0';
        }
    }

    /**
     * Get current currency code
     *
     * @return string Currency code
     */
    private function getCurrencyCode(): string
    {
        try {
            return $this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (\Exception $e) {
            return 'default';
        }
    }

    /**
     * Get customer group ID from request
     *
     * @return string Customer group ID
     */
    private function getCustomerGroupId(): string
    {
        $headers = $this->request->getHeaders();
        return $headers->get('X-Customer-Group-Id') ?: '0';
    }
}
