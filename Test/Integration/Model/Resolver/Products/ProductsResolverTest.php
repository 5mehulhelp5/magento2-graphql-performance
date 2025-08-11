<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Test\Integration\Model\Resolver\Products;

use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Catalog\Api\ProductRepositoryInterface;

class ProductsResolverTest extends GraphQlAbstract
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    protected function setUp(): void
    {
        $this->productRepository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
    }

    /**
     * @magentoApiDataFixture Magento/Catalog/_files/products.php
     */
    public function testBasicProductQuery(): void
    {
        $query = <<<QUERY
query {
    products(
        filter: {
            sku: {
                eq: "simple-product"
            }
        }
    ) {
        items {
            id
            sku
            name
            price_range {
                maximum_price {
                    regular_price {
                        value
                        currency
                    }
                }
            }
        }
        total_count
    }
}
QUERY;

        $response = $this->graphQlQuery($query);

        $this->assertArrayHasKey('products', $response);
        $this->assertArrayHasKey('items', $response['products']);
        $this->assertArrayHasKey('total_count', $response['products']);
        $this->assertCount(1, $response['products']['items']);
        $this->assertEquals('simple-product', $response['products']['items'][0]['sku']);
    }

    /**
     * @magentoApiDataFixture Magento/Catalog/_files/products_with_different_price.php
     */
    public function testProductQueryWithPriceFilter(): void
    {
        $query = <<<QUERY
query {
    products(
        filter: {
            price: {
                from: "50"
                to: "150"
            }
        }
        sort: {
            price: ASC
        }
    ) {
        items {
            sku
            price_range {
                maximum_price {
                    regular_price {
                        value
                    }
                }
            }
        }
        total_count
    }
}
QUERY;

        $response = $this->graphQlQuery($query);

        $this->assertArrayHasKey('products', $response);
        $this->assertArrayHasKey('items', $response['products']);
        
        foreach ($response['products']['items'] as $item) {
            $price = $item['price_range']['maximum_price']['regular_price']['value'];
            $this->assertGreaterThanOrEqual(50, $price);
            $this->assertLessThanOrEqual(150, $price);
        }
    }

    /**
     * @magentoApiDataFixture Magento/Catalog/_files/products_with_different_visibility.php
     */
    public function testProductQueryWithVisibilityFilter(): void
    {
        $query = <<<QUERY
query {
    products(
        filter: {
            visibility: {
                eq: "4"
            }
        }
    ) {
        items {
            sku
            name
        }
        total_count
    }
}
QUERY;

        $response = $this->graphQlQuery($query);

        $this->assertArrayHasKey('products', $response);
        $this->assertArrayHasKey('total_count', $response['products']);
        $this->assertGreaterThan(0, $response['products']['total_count']);
    }

    /**
     * @magentoApiDataFixture Magento/Catalog/_files/product_with_options.php
     */
    public function testProductQueryWithCustomAttributes(): void
    {
        $query = <<<QUERY
query {
    products(
        filter: {
            sku: {
                eq: "simple-product-with-options"
            }
        }
    ) {
        items {
            sku
            name
            es_saat_mekanizma
            es_kasa_capi
            es_kasa_cinsi
            es_kordon_tipi
            manufacturer
        }
    }
}
QUERY;

        $response = $this->graphQlQuery($query);

        $this->assertArrayHasKey('products', $response);
        $this->assertArrayHasKey('items', $response['products']);
        $this->assertCount(1, $response['products']['items']);
        
        $product = $response['products']['items'][0];
        $this->assertEquals('simple-product-with-options', $product['sku']);
        $this->assertArrayHasKey('es_saat_mekanizma', $product);
        $this->assertArrayHasKey('es_kasa_capi', $product);
        $this->assertArrayHasKey('es_kasa_cinsi', $product);
        $this->assertArrayHasKey('es_kordon_tipi', $product);
        $this->assertArrayHasKey('manufacturer', $product);
    }

    /**
     * @magentoApiDataFixture Magento/Catalog/_files/products.php
     */
    public function testProductQueryPagination(): void
    {
        $query = <<<QUERY
query {
    products(
        pageSize: 2
        currentPage: 1
    ) {
        items {
            sku
        }
        page_info {
            total_pages
            current_page
            page_size
        }
        total_count
    }
}
QUERY;

        $response = $this->graphQlQuery($query);

        $this->assertArrayHasKey('products', $response);
        $this->assertArrayHasKey('items', $response['products']);
        $this->assertCount(2, $response['products']['items']);
        $this->assertArrayHasKey('page_info', $response['products']);
        $this->assertEquals(1, $response['products']['page_info']['current_page']);
        $this->assertEquals(2, $response['products']['page_info']['page_size']);
    }
}
