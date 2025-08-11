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

class ProductDataLoaderTest extends TestCase
{
    private readonly ObjectManagerInterface $objectManager;
    private readonly ProductRepositoryAdapter $repository;
    private readonly ProductDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->repository = $this->createMock(ProductRepositoryAdapter::class);

        $this->dataLoader = new ProductDataLoader(
            $this->objectManager,
            $this->repository
        );
    }

    public function testBatchLoad(): void
    {
        $productIds = ['1', '2', '3'];
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $searchResults = $this->createMock(ProductSearchResultsInterface::class);

        // Create mock products
        $products = [];
        foreach ($productIds as $id) {
            $product = $this->createMock(ProductInterface::class);
            $product->expects($this->once())
                ->method('getId')
                ->willReturn($id);
            $products[] = $product;
        }

        // Setup search criteria builder
        $this->searchCriteriaBuilder->expects($this->once())
            ->method('addFilter')
            ->with('entity_id', $productIds, 'in')
            ->willReturnSelf();

        $this->searchCriteriaBuilder->expects($this->once())
            ->method('create')
            ->willReturn($searchCriteria);

        // Setup product repository
        $searchResults->expects($this->once())
            ->method('getItems')
            ->willReturn($products);

        $this->productRepository->expects($this->once())
            ->method('getList')
            ->with($searchCriteria)
            ->willReturn($searchResults);

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

    public function testLoadSingle(): void
    {
        $productId = '1';
        $product = $this->createMock(ProductInterface::class);

        // Setup expectations for batch loading a single product
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $searchResults = $this->createMock(ProductSearchResultsInterface::class);

        $product->expects($this->once())
            ->method('getId')
            ->willReturn($productId);

        $this->searchCriteriaBuilder->expects($this->once())
            ->method('addFilter')
            ->with('entity_id', [$productId], 'in')
            ->willReturnSelf();

        $this->searchCriteriaBuilder->expects($this->once())
            ->method('create')
            ->willReturn($searchCriteria);

        $searchResults->expects($this->once())
            ->method('getItems')
            ->willReturn([$product]);

        $this->productRepository->expects($this->once())
            ->method('getList')
            ->with($searchCriteria)
            ->willReturn($searchResults);

        $result = $this->dataLoader->load($productId);

        $this->assertSame($product, $result);
    }

    public function testLoadMultiple(): void
    {
        $productIds = ['1', '2'];
        $products = [];
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $searchResults = $this->createMock(ProductSearchResultsInterface::class);

        // Create mock products
        foreach ($productIds as $id) {
            $product = $this->createMock(ProductInterface::class);
            $product->expects($this->once())
                ->method('getId')
                ->willReturn($id);
            $products[] = $product;
        }

        $this->searchCriteriaBuilder->expects($this->once())
            ->method('addFilter')
            ->with('entity_id', $productIds, 'in')
            ->willReturnSelf();

        $this->searchCriteriaBuilder->expects($this->once())
            ->method('create')
            ->willReturn($searchCriteria);

        $searchResults->expects($this->once())
            ->method('getItems')
            ->willReturn($products);

        $this->productRepository->expects($this->once())
            ->method('getList')
            ->with($searchCriteria)
            ->willReturn($searchResults);

        $result = $this->dataLoader->loadMany($productIds);

        $this->assertCount(2, $result);
        foreach ($productIds as $id) {
            $this->assertArrayHasKey($id, $result);
        }
    }
}

