<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session as CustomerSession;

class QueryCache extends \Magento\Framework\Cache\Frontend\Decorator\TagScope
{
    public const TYPE_IDENTIFIER = 'graphql_query_cache';
    public const CACHE_TAG = 'GRAPHQL_QUERY';

    private SerializerInterface $serializer;
    private StoreManagerInterface $storeManager;
    private CustomerSession $customerSession;

    public function __construct(
        FrontendPool $cacheFrontendPool,
        SerializerInterface $serializer,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession
    ) {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
        $this->serializer = $serializer;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
    }

    /**
     * Get cached query result
     *
     * @param string $query
     * @param array $variables
     * @return array|null
     */
    public function getQueryResult(string $query, array $variables = []): ?array
    {
        $cacheKey = $this->generateCacheKey($query, $variables);
        $cachedData = $this->load($cacheKey);

        if ($cachedData === false) {
            return null;
        }

        try {
            return $this->serializer->unserialize($cachedData);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Save query result to cache
     *
     * @param string $query
     * @param array $variables
     * @param array $result
     * @param array $tags
     * @param int|null $lifetime
     * @return bool
     */
    public function saveQueryResult(
        string $query,
        array $variables,
        array $result,
        array $tags = [],
        ?int $lifetime = null
    ): bool {
        $cacheKey = $this->generateCacheKey($query, $variables);
        $tags = array_merge([self::CACHE_TAG], $tags);

        try {
            $data = $this->serializer->serialize($result);
            return $this->save($data, $cacheKey, $tags, $lifetime);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate cache key for query
     *
     * @param string $query
     * @param array $variables
     * @return string
     */
    private function generateCacheKey(string $query, array $variables): string
    {
        $keyParts = [
            'query' => $this->normalizeQuery($query),
            'variables' => $variables,
            'store_id' => $this->storeManager->getStore()->getId(),
            'customer_group' => $this->getCustomerGroup()
        ];

        return sha1($this->serializer->serialize($keyParts));
    }

    /**
     * Normalize query for consistent cache keys
     *
     * @param string $query
     * @return string
     */
    private function normalizeQuery(string $query): string
    {
        // Remove whitespace and comments
        $query = preg_replace('/\s+/', ' ', $query);
        $query = preg_replace('/\s*#.*$/', '', $query);

        // Sort fragments alphabetically
        if (preg_match_all('/fragment\s+(\w+)/', $query, $matches)) {
            $fragments = $matches[1];
            sort($fragments);
            foreach ($fragments as $fragment) {
                if (preg_match('/fragment\s+' . $fragment . '\s+[^{]+{[^}]+}/', $query, $match)) {
                    $fragmentDefinitions[$fragment] = $match[0];
                }
            }
            if (isset($fragmentDefinitions)) {
                $query = preg_replace('/fragment\s+\w+\s+[^{]+{[^}]+}/', '', $query);
                $query .= ' ' . implode(' ', $fragmentDefinitions);
            }
        }

        return trim($query);
    }

    /**
     * Get customer group ID
     *
     * @return int
     */
    private function getCustomerGroup(): int
    {
        return $this->customerSession->getCustomerGroupId() ?? 0;
    }
}
