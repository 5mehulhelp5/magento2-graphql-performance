<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Test\Unit\Model\DataLoader;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;
use Sterk\GraphQlPerformance\Model\DataLoader\ProductDataLoader;
use Sterk\GraphQlPerformance\Model\Repository\ProductRepositoryAdapter;

/**
 * Unit test for ProductDataLoader
 */
class ProductDataLoaderTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var ProductRepositoryAdapter
     */
    private ProductRepositoryAdapter $repository;

    /**
     * @var ProductDataLoader
     */
    private ProductDataLoader $dataLoader;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->repository = $this->createMock(ProductRepositoryAdapter::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);

        $this->dataLoader = new ProductDataLoader(
            $this->objectManager,
            $this->repository
        );
    }

    /**
     * Test batch loading of products
     */
    public function testBatchLoad(): void
    {
        $productIds = ['1', '2', '3'];
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $searchResults = $this->createMock(ProductSearchResultsInterface::class);

        // Create mock products
        $products = [];
        foreach ($productIds as $id) {
            $product = $this->createMock(ProductInterface::class);
            $products[] = $product;
        }

        // Setup search criteria builder
        // Setup product repository
        $this->repository->expects($this->once())
            ->method('getByIds')
            ->with(array_values($productIds))
            ->willReturn(array_combine($productIds, $products));

        // Call the method through reflection since it's protected
        $method = new \ReflectionMethod(ProductDataLoader::class, 'batchLoad');
        $method->setAccessible(true);
        $result = $method->invoke($this->dataLoader, $productIds);

        // Verify results
        $this->assertCount(3, $result);
        foreach ($productIds as $id) {
            $this->assertArrayHasKey($id, $result);
        }
    }

    /**
     * Test loading a single product
     */
    public function testLoadSingle(): void
    {
        $productId = '1';
        $product = $this->createMock(ProductInterface::class);

        // No need to mock getId as we're using array keys

        // Setup repository mock
        $this->repository->expects($this->once())
            ->method('getByIds')
            ->with([$productId])
            ->willReturn([$productId => $product]);

        $result = $this->dataLoader->load($productId);

        $this->assertSame($product, $result);
    }

    /**
     * Test loading multiple products
     */
    public function testLoadMultiple(): void
    {
        $productIds = ['1', '2'];
        $products = [];
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $searchResults = $this->createMock(ProductSearchResultsInterface::class);

        // Create mock products
        foreach ($productIds as $id) {
            $product = $this->createMock(ProductInterface::class);
            $products[] = $product;
        }

        // Setup repository mock
        $this->repository->expects($this->once())
            ->method('getByIds')
            ->with(array_values($productIds))
            ->willReturn(array_combine($productIds, $products));

        $result = $this->dataLoader->loadMany($productIds);

        $this->assertCount(2, $result);
        foreach ($productIds as $id) {
            $this->assertArrayHasKey($id, $result);
        }
    }
}
