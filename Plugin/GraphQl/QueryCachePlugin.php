<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\GraphQl\Schema;
use Magento\GraphQl\Model\Query\Context;
use Sterk\GraphQlPerformance\Model\Cache\QueryCache;
use Sterk\GraphQlPerformance\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Plugin for caching GraphQL query results
 *
 * This plugin implements caching for GraphQL queries, improving performance by
 * serving cached results when available. It handles cache key generation,
 * cache tagging, and cache lifetime management based on query type.
 */
class QueryCachePlugin
{
    /**
     * @param QueryCache $queryCache Query cache service
     * @param Config $config Configuration service
     * @param LoggerInterface $logger Logger service
     */
    public function __construct(
        private readonly QueryCache $queryCache,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Around plugin for query processing
     *
     * @param QueryProcessor $subject Query processor instance
     * @param \Closure $proceed Original method
     * @param Schema $schema GraphQL schema
     * @param string|null $source GraphQL query source
     * @param Context|null $context Query context
     * @param array|null $variables Query variables
     * @param string|null $operationName Operation name
     * @param array|null $extensions GraphQL extensions
     * @return array Query result
     */
    public function aroundProcess(
        QueryProcessor $subject,
        \Closure $proceed,
        Schema $schema,
        ?string $source = null,
        ?Context $context = null,
        ?array $variables = null,
        ?string $operationName = null,
        ?array $extensions = null
    ): array {
        if (empty($source)) {
            return $proceed($schema, $source, $context, $variables, $operationName, $extensions);
        }

        // Skip caching for mutations
        if ($this->isMutation($source)) {
            return $proceed($schema, $source, $context, $variables, $operationName, $extensions);
        }

        // Try to get from cache
        $cachedResult = $this->queryCache->getQueryResult($source, $variables ?? []);
        if ($cachedResult !== null) {
            $this->logger->debug(
                'GraphQL query cache hit',
                [
                    'query' => $source,
                    'operation' => $operationName,
                    'variables' => $variables
                ]
            );
            return $cachedResult;
        }

        try {
            // Execute query
            $result = $proceed($schema, $source, $context, $variables, $operationName, $extensions);

            // Handle empty or null results
            if (!isset($result['data'])) {
                $result['data'] = [];
            }

            // Extract operation name from query
            $operationType = '';
            if (preg_match('/query\s+(\w+)/', $source, $matches)) {
                $operationType = $matches[1];
            }

            // Handle specific query types
            switch ($operationType) {
                case 'GetCategoryUidByName':
                    return $this->handleCategoryByName($result, $variables);
                case 'StoreConfig':
                    return $this->handleStoreConfig($result);
                case 'GetCmsPage':
                    return $this->handleCmsPage($result, $variables);
                case 'ProductPage2':
                    return $this->handleProductPage($result, $variables, $schema, $source, $context, $proceed, $operationName, $extensions);
                case 'ExtendedProductList':
                    return $this->handleProductList($result, $variables, $schema, $source, $context, $proceed, $operationName, $extensions);
            }

            // Handle categories
            if (stripos($source, 'categories') !== false) {
                // Handle category lookup by name
                if (isset($variables['filters']['name']['match'])) {
                    $name = $variables['filters']['name']['match'];
                    // Log category name lookup
                    $this->logger->info('Category name lookup', [
                        'name' => $name,
                        'query' => $source
                    ]);

                    // If no results, return empty structure
                    if (!isset($result['data']['categories']) || empty($result['data']['categories']['items'])) {
                        return [
                            'data' => [
                                'categories' => [
                                    'items' => [],
                                    'total_count' => 0,
                                    'page_info' => [
                                        'total_pages' => 0,
                                        'current_page' => 1,
                                        'page_size' => 20
                                    ]
                                ]
                            ]
                        ];
                    }
                }

                // Handle category lookup by URL path
                if (isset($variables['url'])) {
                    $path = $variables['url'];
                    // Remove leading/trailing slashes
                    $path = trim($path, '/');
                    // Convert Turkish characters to their URL-safe equivalents
                    $turkishChars = ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
                    $englishChars = ['i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'];
                    $path = str_replace($turkishChars, $englishChars, $path);
                    // Update the variables array with normalized path
                    $variables['url'] = $path;
                    // Try the query again with normalized path
                    $result = $proceed($schema, $source, $context, $variables, $operationName, $extensions);
                }

                if (!isset($result['data']['categories']) || empty($result['data']['categories']['items'])) {
                    $path = $variables['url'] ?? '';

                    // Log the category lookup attempt
                    $this->logger->info('Category lookup failed', [
                        'path' => $path,
                        'original_query' => $source,
                        'variables' => $variables
                    ]);

                    // Try to get parent category if this is a subcategory
                    $pathParts = explode('/', $path);
                    if (count($pathParts) > 1) {
                        array_pop($pathParts);
                        $parentPath = implode('/', $pathParts);
                        $variables['url'] = $parentPath;
                        $parentResult = $proceed($schema, $source, $context, $variables, $operationName, $extensions);

                        if (isset($parentResult['data']['categories']['items'][0])) {
                            // Parent category exists, return 404 with parent info
                            return [
                                'data' => [
                                    'categories' => [
                                        'items' => [],
                                        'total_count' => 0,
                                        'page_info' => [
                                            'total_pages' => 0,
                                            'current_page' => 1,
                                            'page_size' => $variables['pageSize'] ?? 20
                                        ],
                                        'parent_category' => $parentResult['data']['categories']['items'][0]
                                    ]
                                ],
                                'errors' => [
                                    [
                                        'message' => __('Category not found for path: %1', $path),
                                        'extensions' => [
                                            'category' => 'graphql-no-such-entity',
                                            'path' => $path,
                                            'parent_path' => $parentPath
                                        ]
                                    ]
                                ]
                            ];
                        }
                    }

                    // No parent found or not a subcategory
                    return [
                        'data' => [
                            'categories' => [
                                'items' => [],
                                'total_count' => 0,
                                'page_info' => [
                                    'total_pages' => 0,
                                    'current_page' => 1,
                                    'page_size' => $variables['pageSize'] ?? 20
                                ]
                            ]
                        ],
                        'errors' => [
                            [
                                'message' => __('Category not found for path: %1', $path),
                                'extensions' => [
                                    'category' => 'graphql-no-such-entity',
                                    'path' => $path
                                ]
                            ]
                        ]
                    ];
                }
            }

            // Handle products
            if (stripos($source, 'products') !== false) {
                // Handle product list query
                if (isset($variables['filter']) || isset($variables['filters'])) {
                    $filters = $variables['filter'] ?? $variables['filters'] ?? [];

                    // Log product search attempt
                    $this->logger->info('Product search', [
                        'filters' => $filters,
                        'sort' => $variables['sort'] ?? null,
                        'search' => $variables['search'] ?? null,
                        'page' => $variables['currentPage'] ?? 1
                    ]);

                    // Handle category-based product listing
                    if (isset($filters['category_uid']['eq'])) {
                        $categoryId = $filters['category_uid']['eq'];

                        // If no products found for category, try to get category info
                        if (empty($result['data']['products']['items'])) {
                            $categoryQuery = 'query ($id: String!) { categories(filters: {uid: {eq: $id}}) { items { uid name url_path } } }';
                            $categoryVars = ['id' => $categoryId];
                            $categoryResult = $proceed($schema, $categoryQuery, $context, $categoryVars, null, $extensions);

                            if (!empty($categoryResult['data']['categories']['items'])) {
                                $category = $categoryResult['data']['categories']['items'][0];
                                return [
                                    'data' => [
                                        'products' => [
                                            'items' => [],
                                            'total_count' => 0,
                                            'page_info' => [
                                                'total_pages' => 0,
                                                'current_page' => $variables['currentPage'] ?? 1,
                                                'page_size' => $variables['pageSize'] ?? 24
                                            ],
                                            'aggregations' => [],
                                            'sort_fields' => [
                                                'options' => [
                                                    ['label' => 'Position', 'value' => 'position'],
                                                    ['label' => 'Product Name', 'value' => 'name'],
                                                    ['label' => 'Price', 'value' => 'price']
                                                ]
                                            ],
                                            'category' => $category
                                        ]
                                    ]
                                ];
                            }
                        }
                    }

                                        // Handle URL key based product detail
                    if (isset($filters['url_key']['eq'])) {
                        $urlKey = $filters['url_key']['eq'];

                        // If product not found by URL key
                        if (empty($result['data']['products']['items'])) {
                            // Try to normalize the URL key
                            $normalizedUrlKey = $this->normalizeUrlKey($urlKey);
                            if ($normalizedUrlKey !== $urlKey) {
                                $filters['url_key']['eq'] = $normalizedUrlKey;
                                $variables['filter'] = $filters;
                                $result = $proceed($schema, $source, $context, $variables, $operationName, $extensions);
                            }

                            // If still not found, try to get related info
                            if (empty($result['data']['products']['items'])) {
                                // Try to get manufacturer info if present in URL
                                if (preg_match('/^([a-zA-Z0-9-]+)-[a-zA-Z0-9-]+$/', $urlKey, $matches)) {
                                    $brandName = str_replace('-', ' ', $matches[1]);
                                    $brandQuery = 'query ($name: String!) { products(filter: {manufacturer: {like: $name}}, pageSize: 1) { items { manufacturer { label } } } }';
                                    $brandVars = ['name' => $brandName];
                                    $brandResult = $proceed($schema, $brandQuery, $context, $brandVars, null, $extensions);

                                    if (!empty($brandResult['data']['products']['items'][0]['manufacturer'])) {
                                        return [
                                            'data' => [
                                                'products' => [
                                                    'items' => [],
                                                    'total_count' => 0,
                                                    'manufacturer_info' => $brandResult['data']['products']['items'][0]['manufacturer']
                                                ]
                                            ],
                                            'errors' => [
                                                [
                                                    'message' => __('Product not found: %1', $urlKey),
                                                    'extensions' => [
                                                        'category' => 'graphql-no-such-entity',
                                                        'url_key' => $urlKey,
                                                        'manufacturer' => $brandName
                                                    ]
                                                ]
                                            ]
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }

                // Ensure all required fields are present
                if (!isset($result['data']['products'])) {
                    $result['data']['products'] = [];
                }
                if (!isset($result['data']['products']['items'])) {
                    $result['data']['products']['items'] = [];
                }
                if (!isset($result['data']['products']['aggregations'])) {
                    $result['data']['products']['aggregations'] = $this->getDefaultAggregations();
                } else {
                    // Ensure each aggregation has at least an empty options array
                    foreach ($result['data']['products']['aggregations'] as &$aggregation) {
                        if (!isset($aggregation['options'])) {
                            $aggregation['options'] = [];
                        }
                    }
                }
                if (!isset($result['data']['products']['page_info'])) {
                    $result['data']['products']['page_info'] = [
                        'total_pages' => 0,
                        'current_page' => $variables['currentPage'] ?? 1,
                        'page_size' => $variables['pageSize'] ?? 24
                    ];
                }
                if (!isset($result['data']['products']['total_count'])) {
                    $result['data']['products']['total_count'] = 0;
                }
                if (!isset($result['data']['products']['sort_fields'])) {
                    $result['data']['products']['sort_fields'] = [
                        'options' => [
                            ['label' => 'Position', 'value' => 'position'],
                            ['label' => 'Product Name', 'value' => 'name'],
                            ['label' => 'Price', 'value' => 'price'],
                            ['label' => 'Newest', 'value' => 'created_at'],
                            ['label' => 'Best Sellers', 'value' => 'bestsellers']
                        ]
                    ];
                }
            }

            // Handle store config
            if (stripos($source, 'storeConfig') !== false && !isset($result['data']['storeConfig'])) {
                $this->logger->warning('StoreConfig query returned no data');
                return [
                    'data' => [
                        'storeConfig' => [
                            'store_code' => 'default',
                            'locale' => 'tr_TR',
                            'base_currency_code' => 'TRY',
                            'default_display_currency_code' => 'TRY',
                            'grid_per_page' => 24,
                            'grid_per_page_values' => '12,24,36',
                            'list_per_page' => 24
                        ]
                    ]
                ];
            }

            // Handle CMS pages
            if (stripos($source, 'cmsPage') !== false && !isset($result['data']['cmsPage'])) {
                $identifier = $variables['identifier'] ?? '';
                return [
                    'data' => null,
                    'errors' => [
                        [
                            'message' => __('No CMS page found for identifier: %1', $identifier),
                            'extensions' => [
                                'category' => 'graphql-no-such-entity',
                                'identifier' => $identifier
                            ]
                        ]
                    ]
                ];
            }

            // Cache the result if no errors
            if (!isset($result['errors'])) {
            $lifetime = $this->getCacheLifetime($source);
            $tags = $this->getCacheTags($result);

            $this->queryCache->saveQueryResult(
                $source,
                $variables ?? [],
                $result,
                $tags,
                $lifetime
            );

            $this->logger->debug(
                'GraphQL query cached',
                [
                    'query' => $source,
                    'operation' => $operationName,
                    'variables' => $variables,
                    'lifetime' => $lifetime,
                    'tags' => $tags
                ]
            );
        }

        return $result;
        } catch (\Exception $e) {
            $context = [
                'query' => $source,
                'operation' => $operationName,
                'variables' => $variables,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

            // Log detailed error information
            $this->logger->error('GraphQL query error: ' . $e->getMessage(), $context);

            // Log specific error types
            if ($e instanceof \Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException) {
                $this->logger->info('Entity not found', $context);
            } elseif ($e instanceof \Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException) {
                $this->logger->warning('Authorization error', $context);
            } elseif ($e instanceof \Magento\Framework\GraphQl\Exception\GraphQlInputException) {
                $this->logger->warning('Invalid input', $context);
            }
            return [
                'data' => null,
                'errors' => [
                    [
                        'message' => $e->getMessage(),
                        'extensions' => [
                            'category' => 'graphql'
                        ]
                    ]
                ]
            ];
        }
    }

    /**
     * Check if query is a mutation
     *
     * @param  string $query
     * @return bool
     */
    private function isMutation(string $query): bool
    {
        return stripos(trim($query), 'mutation') === 0;
    }

    /**
     * Get cache lifetime for query
     *
     * @param  string $query
     * @return int
     */
    private function getCacheLifetime(string $query): int
    {
        // Use different lifetimes based on query type and fields
        if (stripos($query, 'products') !== false) {
            // Shorter cache for product queries with stock or price
            if (stripos($query, 'stock_status') !== false || stripos($query, 'price_range') !== false) {
                return 300; // 5 minutes for dynamic data
            }

            // Shorter cache for filtered product queries
            if (stripos($query, 'es_outlet_urun') !== false || stripos($query, 'es_webe_ozel') !== false) {
                return 600; // 10 minutes for filtered products
            }

            return $this->config->getResolverCacheLifetime('product');
        }

        if (stripos($query, 'categories') !== false) {
            // Longer cache for brand categories
            if (stripos($query, 'is_brand') !== false || stripos($query, 'manufacturer') !== false) {
                return 86400; // 24 hours for brand data
            }

            return $this->config->getResolverCacheLifetime('category');
        }

        if (stripos($query, 'cart') !== false) {
            return $this->config->getResolverCacheLifetime('cart');
        }

        // Special handling for Edip Saat custom queries
        if (stripos($query, 'brandCategories') !== false || stripos($query, 'GetAllCakmakCategories') !== false) {
            return 86400; // 24 hours for brand listings
        }

        return $this->config->getCacheLifetime();
    }

    /**
     * Get cache tags from result
     *
     * @param  array $result
     * @return array
     */
    private function getCacheTags(array $result): array
    {
        $tags = [QueryCache::CACHE_TAG];

        // Add entity-specific tags
        if (isset($result['data'])) {
            if (isset($result['data']['products'])) {
                $tags[] = 'catalog_product';
                foreach ($result['data']['products']['items'] ?? [] as $product) {
                    if (isset($product['id'])) {
                        $tags[] = 'catalog_product_' . $product['id'];
                    }
                    if (isset($product['uid'])) {
                        $tags[] = 'catalog_product_' . $product['uid'];
                    }
                    // Add manufacturer tag for better cache invalidation
                    if (isset($product['manufacturer']['label'])) {
                        $tags[] = 'manufacturer_' . strtolower(str_replace(' ', '_', $product['manufacturer']['label']));
                    }
                }
            }

            if (isset($result['data']['categories'])) {
                $tags[] = 'catalog_category';
                foreach ($result['data']['categories']['items'] ?? [] as $category) {
                    if (isset($category['id'])) {
                        $tags[] = 'catalog_category_' . $category['id'];
                    }
                    if (isset($category['uid'])) {
                        $tags[] = 'catalog_category_' . $category['uid'];
                    }
                }
            }

            if (isset($result['data']['cart'])) {
                $tags[] = 'quote';
                if (isset($result['data']['cart']['id'])) {
                    $tags[] = 'quote_' . $result['data']['cart']['id'];
                }
            }
        }

        return array_unique($tags);
    }

    /**
     * Normalize URL key for product lookup
     *
     * @param string $urlKey
     * @return string
     */
    private function normalizeUrlKey(string $urlKey): string
    {
        // Remove leading/trailing slashes and spaces
        $urlKey = trim($urlKey, '/ ');

        // Convert Turkish characters to URL-safe equivalents
        $turkishChars = ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
        $englishChars = ['i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'];
        $urlKey = str_replace($turkishChars, $englishChars, $urlKey);

        // Convert to lowercase and replace spaces with hyphens
        $urlKey = strtolower(str_replace(' ', '-', $urlKey));

        // Remove any invalid characters
        $urlKey = preg_replace('/[^a-z0-9\-]/', '', $urlKey);

        // Remove multiple consecutive hyphens
        $urlKey = preg_replace('/-+/', '-', $urlKey);

        return $urlKey;
    }

    /**
     * Get default aggregations for product listing
     *
     * @return array
     */
    /**
     * Handle GetCategoryUidByName query
     *
     * @param array $result
     * @param array $variables
     * @return array
     */
        private function handleCategoryByName(array $result, array $variables): array
    {
        $name = $variables['name'] ?? '';

        // Default category structure
        $defaultCategory = [
            'items' => [],
            'total_count' => 0,
            'page_info' => [
                'total_pages' => 0,
                'current_page' => 1,
                'page_size' => 20,
                '__typename' => 'SearchResultPageInfo'
            ],
            '__typename' => 'CategoryResult'
        ];

        // If no data or empty items
        if (!isset($result['data']['categories']) || empty($result['data']['categories']['items'])) {
            $this->logger->info('No categories found for name: ' . $name);

            // Try to find parent category
            if ($name && strpos($name, '/') !== false) {
                $parts = explode('/', $name);
                array_pop($parts);
                $parentName = implode('/', $parts);

                $this->logger->info('Trying parent category: ' . $parentName);

                return [
                    'data' => [
                        'categories' => array_merge($defaultCategory, [
                            'parent_name' => $parentName
                        ])
                    ],
                    'errors' => [
                        [
                            'message' => __('Category not found: %1', $name),
                            'extensions' => [
                                'category' => 'graphql-no-such-entity',
                                'name' => $name,
                                'parent_name' => $parentName
                            ]
                        ]
                    ]
                ];
            }

            // Return default structure for non-nested categories
            return [
                'data' => [
                    'categories' => $defaultCategory
                ]
            ];
        }

        // Ensure all required fields are present
        $categoryData = array_merge(
            $defaultCategory,
            $result['data']['categories'],
            [
                'total_count' => count($result['data']['categories']['items']),
                'page_info' => [
                    'total_pages' => ceil(count($result['data']['categories']['items']) / 20),
                    'current_page' => 1,
                    'page_size' => 20,
                    '__typename' => 'SearchResultPageInfo'
                ]
            ]
        );

        return [
            'data' => [
                'categories' => $categoryData
            ]
        ];
    }

    /**
     * Handle StoreConfig query
     *
     * @param array $result
     * @return array
     */
    private function handleStoreConfig(array $result): array
    {
        $defaultConfig = [
            'website_name' => 'Edip Saat',
            'store_code' => 'default',
            'store_name' => 'Edip Saat',
            'locale' => 'tr_TR',
            'base_currency_code' => 'TRY',
            'default_display_currency_code' => 'TRY',
            'title_suffix' => ' | Edip Saat',
            'title_prefix' => '',
            'title_separator' => ' - ',
            'default_title' => 'Edip Saat',
            'cms_home_page' => 'home',
            'catalog_default_sort_by' => 'position',
            'category_url_suffix' => '',
            'product_url_suffix' => '',
            'secure_base_link_url' => 'https://staging-worker.edipsaat.com/',
            'secure_base_url' => 'https://staging-worker.edipsaat.com/',
            'root_category_uid' => 'Mg==',
            'weight_unit' => 'kgs',
            'product_reviews_enabled' => true,
            'allow_guests_to_write_product_reviews' => true,
            'grid_per_page' => 24,
            'grid_per_page_values' => '12,24,36',
            'list_per_page' => 24,
            'create_account_confirmation' => false,
            'order_cancellation_enabled' => true,
            'order_cancellation_reasons' => [
                [
                    'description' => 'Ürünü artık istemiyorum',
                    '__typename' => 'OrderCancellationReason'
                ],
                [
                    'description' => 'Yanlış ürün seçtim',
                    '__typename' => 'OrderCancellationReason'
                ],
                [
                    'description' => 'Diğer',
                    '__typename' => 'OrderCancellationReason'
                ]
            ],
            'autocomplete_on_storefront' => true,
            'minimum_password_length' => 8,
            'required_character_classes_number' => 3,
            'magento_wishlist_general_is_enabled' => true,
            '__typename' => 'StoreConfig'
        ];

        if (!isset($result['data']['storeConfig']) || empty($result['data']['storeConfig'])) {
            $this->logger->info('Using default store config');
            return [
                'data' => [
                    'storeConfig' => $defaultConfig
                ]
            ];
        }

        // Merge with defaults to ensure all fields are present
        $result['data']['storeConfig'] = array_merge(
            $defaultConfig,
            $result['data']['storeConfig']
        );

        return $result;
    }

    /**
     * Handle GetCmsPage query
     *
     * @param array $result
     * @param array $variables
     * @return array
     */
    private function handleCmsPage(array $result, array $variables): array
    {
        $identifier = $variables['identifier'] ?? '';

        if (!isset($result['data']['cmsPage']) || $result['data']['cmsPage'] === null) {
            if ($identifier === 'no-route') {
                return [
                    'data' => [
                        'cmsPage' => null
                    ],
                    'errors' => [
                        [
                            'message' => __('Page not found'),
                            'extensions' => [
                                'category' => 'graphql-no-such-entity',
                                'identifier' => $identifier
                            ]
                        ]
                    ]
                ];
            }
        }

        return $result;
    }

    /**
     * Handle ProductPage2 query
     *
     * @param array $result
     * @param array $variables
     * @param Schema $schema
     * @param string $source
     * @param mixed $context
     * @param \Closure $proceed
     * @param string|null $operationName
     * @param array|null $extensions
     * @return array
     */
    private function handleProductPage(
        array $result,
        array $variables,
        Schema $schema,
        string $source,
        $context,
        \Closure $proceed,
        ?string $operationName,
        ?array $extensions
    ): array {
        // Initialize empty product data structure
        if (!isset($result['data']['products'])) {
            $result['data']['products'] = [
                'items' => [],
                'total_count' => 0
            ];
        }

        // Ensure each product has price data
        if (!empty($result['data']['products']['items'])) {
            foreach ($result['data']['products']['items'] as &$product) {
                if (!isset($product['price_range'])) {
                    $product['price_range'] = [
                        'maximum_price' => [
                            'final_price' => [
                                'value' => 0,
                                'currency' => 'TRY'
                            ],
                            'regular_price' => [
                                'value' => 0,
                                'currency' => 'TRY'
                            ],
                            'discount' => [
                                'amount_off' => 0,
                                'percent_off' => 0
                            ]
                        ],
                        'minimum_price' => [
                            'final_price' => [
                                'value' => 0,
                                'currency' => 'TRY'
                            ],
                            'regular_price' => [
                                'value' => 0,
                                'currency' => 'TRY'
                            ],
                            'discount' => [
                                'amount_off' => 0,
                                'percent_off' => 0
                            ]
                        ]
                    ];
                }

                // Ensure stock status is present
                if (!isset($product['stock_status'])) {
                    $product['stock_status'] = 'OUT_OF_STOCK';
                }

                // Add required fields for price display
                if (!isset($product['url_key'])) {
                    $product['url_key'] = '';
                }
            }
        if (empty($result['data']['products']['items'])) {
            $urlKey = $variables['urlKey'] ?? '';

            // Try to get manufacturer info
            if (preg_match('/^([a-zA-Z0-9-]+)-[a-zA-Z0-9-]+$/', $urlKey, $matches)) {
                $brandName = str_replace('-', ' ', $matches[1]);
                $brandQuery = 'query ($name: String!) { products(filter: {manufacturer: {like: $name}}, pageSize: 1) { items { manufacturer { label } } } }';
                $brandVars = ['name' => $brandName];
                $brandResult = $proceed($schema, $brandQuery, $context, $brandVars, null, $extensions);

                if (!empty($brandResult['data']['products']['items'][0]['manufacturer'])) {
                    return [
                        'data' => [
                            'products' => [
                                'items' => [],
                                'total_count' => 0,
                                'manufacturer_info' => $brandResult['data']['products']['items'][0]['manufacturer']
                            ]
                        ],
                        'errors' => [
                            [
                                'message' => __('Product not found: %1', $urlKey),
                                'extensions' => [
                                    'category' => 'graphql-no-such-entity',
                                    'url_key' => $urlKey,
                                    'manufacturer' => $brandName
                                ]
                            ]
                        ]
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Handle ExtendedProductList query
     *
     * @param array $result
     * @param array $variables
     * @param Schema $schema
     * @param string $source
     * @param mixed $context
     * @param \Closure $proceed
     * @param string|null $operationName
     * @param array|null $extensions
     * @return array
     */
    private function handleProductList(
        array $result,
        array $variables,
        Schema $schema,
        string $source,
        $context,
        \Closure $proceed,
        ?string $operationName,
        ?array $extensions
    ): array {
        if (!isset($result['data']['products'])) {
            $result['data']['products'] = [];
        }

        // Ensure all required fields are present
        $result['data']['products']['items'] = $result['data']['products']['items'] ?? [];
        $result['data']['products']['total_count'] = count($result['data']['products']['items']);

        // Handle aggregations
        if (!isset($result['data']['products']['aggregations'])) {
            $result['data']['products']['aggregations'] = $this->getDefaultAggregations();
        } else {
            foreach ($result['data']['products']['aggregations'] as &$aggregation) {
                $aggregation['options'] = $aggregation['options'] ?? [];
            }
        }

        // Handle page info
        $result['data']['products']['page_info'] = [
            'current_page' => $variables['currentPage'] ?? 1,
            'page_size' => $variables['pageSize'] ?? 24,
            'total_pages' => ceil(($result['data']['products']['total_count'] ?? 0) / ($variables['pageSize'] ?? 24))
        ];

        // Handle sort fields
        $result['data']['products']['sort_fields'] = [
            'options' => [
                ['label' => 'Position', 'value' => 'position'],
                ['label' => 'Product Name', 'value' => 'name'],
                ['label' => 'Price', 'value' => 'price'],
                ['label' => 'Newest', 'value' => 'created_at'],
                ['label' => 'Best Sellers', 'value' => 'bestsellers']
            ]
        ];

        return $result;
    }

    private function getDefaultAggregations(): array
    {
        return [
            [
                'attribute_code' => 'manufacturer',
                'label' => 'Manufacturer',
                'options' => []
            ],
            [
                'attribute_code' => 'es_kasa_capi',
                'label' => 'Kasa Çapı',
                'options' => []
            ],
            [
                'attribute_code' => 'es_saat_mekanizma',
                'label' => 'Saat Mekanizması',
                'options' => []
            ],
            [
                'attribute_code' => 'es_kasa_cinsi',
                'label' => 'Kasa Cinsi',
                'options' => []
            ],
            [
                'attribute_code' => 'es_kordon_tipi',
                'label' => 'Kordon Tipi',
                'options' => []
            ],
            [
                'attribute_code' => 'es_swiss_made',
                'label' => 'Swiss Made',
                'options' => []
            ],
            [
                'attribute_code' => 'es_webe_ozel',
                'label' => 'Web\'e Özel',
                'options' => []
            ],
            [
                'attribute_code' => 'es_outlet_urun',
                'label' => 'Outlet Ürün',
                'options' => []
            ],
            [
                'attribute_code' => 'es_teklife_acik',
                'label' => 'Teklife Açık',
                'options' => []
            ]
        ];
    }
}
