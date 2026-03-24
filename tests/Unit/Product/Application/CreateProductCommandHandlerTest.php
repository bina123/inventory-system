<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Application;

use App\Module\Product\Application\Command\CreateProductCommand;
use App\Module\Product\Application\Command\CreateProductCommandHandler;
use App\Module\Product\Domain\Category;
use App\Module\Product\Domain\CategoryRepositoryInterface;
use App\Module\Product\Domain\Exception\CategoryNotFoundException;
use App\Module\Product\Domain\Exception\DuplicateSkuException;
use App\Module\Product\Domain\ProductRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class CreateProductCommandHandlerTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;
    private CategoryRepositoryInterface&MockObject $categoryRepository;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private CreateProductCommandHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository  = $this->createMock(ProductRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->eventDispatcher    = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new CreateProductCommandHandler(
            productRepository:  $this->productRepository,
            categoryRepository: $this->categoryRepository,
            eventDispatcher:    $this->eventDispatcher,
        );
    }

    public function test_creates_product_successfully(): void
    {
        $category = new Category('Electronics');

        $this->categoryRepository
            ->method('findByUuid')
            ->with('cat-uuid-1')
            ->willReturn($category);

        $this->productRepository
            ->method('findBySku')
            ->willReturn(null); // no duplicate

        $this->productRepository->expects(self::once())->method('save');
        $this->eventDispatcher->expects(self::atLeastOnce())->method('dispatch');

        $command = new CreateProductCommand(
            name:         'Widget Pro',
            sku:          'WGT-PRO-001',
            price:        29.99,
            currency:     'USD',
            categoryUuid: 'cat-uuid-1',
            description:  'A great widget',
        );

        $response = $this->handler->handle($command);

        self::assertSame('WGT-PRO-001', $response->sku);
        self::assertSame('Widget Pro', $response->name);
        self::assertEqualsWithDelta(29.99, $response->price, 0.01);
        self::assertSame('USD', $response->currency);
        self::assertTrue($response->isActive);
    }

    public function test_throws_when_sku_already_exists(): void
    {
        $existingProduct = $this->createMock(\App\Module\Product\Domain\Product::class);

        $this->productRepository
            ->method('findBySku')
            ->willReturn($existingProduct);

        $this->expectException(DuplicateSkuException::class);

        $command = new CreateProductCommand(
            name:         'Widget',
            sku:          'WGT-001',
            price:        10.00,
            currency:     'USD',
            categoryUuid: 'cat-uuid-1',
        );

        $this->handler->handle($command);
    }

    public function test_throws_when_category_not_found(): void
    {
        $this->productRepository->method('findBySku')->willReturn(null);
        $this->categoryRepository->method('findByUuid')->willReturn(null);

        $this->expectException(CategoryNotFoundException::class);

        $command = new CreateProductCommand(
            name:         'Widget',
            sku:          'WGT-001',
            price:        10.00,
            currency:     'USD',
            categoryUuid: 'missing-cat-uuid',
        );

        $this->handler->handle($command);
    }

    public function test_throws_on_invalid_sku_format(): void
    {
        $this->productRepository->method('findBySku')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $command = new CreateProductCommand(
            name:         'Widget',
            sku:          'AB', // too short — Sku VO will throw
            price:        10.00,
            currency:     'USD',
            categoryUuid: 'cat-uuid-1',
        );

        $this->handler->handle($command);
    }
}
