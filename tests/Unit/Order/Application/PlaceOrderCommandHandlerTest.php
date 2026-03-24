<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application;

use App\Module\Inventory\Domain\Exception\InsufficientStockException;
use App\Module\Inventory\Domain\InventoryItem;
use App\Module\Inventory\Domain\InventoryRepositoryInterface;
use App\Module\Order\Application\Command\PlaceOrderCommand;
use App\Module\Order\Application\Command\PlaceOrderCommandHandler;
use App\Module\Order\Domain\OrderRepositoryInterface;
use App\Module\Product\Domain\Category;
use App\Module\Product\Domain\Product;
use App\Module\Product\Domain\ProductRepositoryInterface;
use App\Module\Product\Domain\ValueObject\Money;
use App\Module\Product\Domain\ValueObject\Sku;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class PlaceOrderCommandHandlerTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;
    private InventoryRepositoryInterface&MockObject $inventoryRepository;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private PlaceOrderCommandHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository   = $this->createMock(ProductRepositoryInterface::class);
        $this->inventoryRepository = $this->createMock(InventoryRepositoryInterface::class);
        $this->orderRepository     = $this->createMock(OrderRepositoryInterface::class);
        $this->eventDispatcher     = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new PlaceOrderCommandHandler(
            orderRepository:     $this->orderRepository,
            productRepository:   $this->productRepository,
            inventoryRepository: $this->inventoryRepository,
            eventDispatcher:     $this->eventDispatcher,
        );
    }

    private function makeProduct(string $uuid = 'prod-uuid-1'): Product
    {
        $product = new Product(
            name:     'Widget Pro',
            sku:      new Sku('WGT-001'),
            price:    Money::fromFloat(29.99),
            category: new Category('Electronics'),
        );

        // Ensure product UUID matches what we expect via reflection
        $reflection = new \ReflectionProperty(Product::class, 'uuid');
        $reflection->setValue($product, $uuid);

        return $product;
    }

    private function makeInventoryItem(string $productUuid, int $qty = 50): InventoryItem
    {
        return new InventoryItem(
            productId:        1,
            productUuid:      $productUuid,
            productSku:       'WGT-001',
            productName:      'Widget Pro',
            initialQuantity:  $qty,
            lowStockThreshold: 10,
        );
    }

    public function test_place_order_succeeds_with_sufficient_stock(): void
    {
        $productUuid   = 'prod-uuid-1';
        $product       = $this->makeProduct($productUuid);
        $inventoryItem = $this->makeInventoryItem($productUuid, 50);

        $this->productRepository
            ->method('findByUuid')
            ->with($productUuid)
            ->willReturn($product);

        $this->inventoryRepository
            ->method('findByProductUuid')
            ->with($productUuid)
            ->willReturn($inventoryItem);

        $this->orderRepository->expects(self::once())->method('save');
        $this->inventoryRepository->expects(self::once())->method('save');
        $this->eventDispatcher->expects(self::atLeastOnce())->method('dispatch');

        $command = new PlaceOrderCommand(
            customerEmail: 'buyer@example.com',
            items: [['productUuid' => $productUuid, 'quantity' => 5]],
        );

        $response = $this->handler->handle($command);

        self::assertSame('buyer@example.com', $response->customerEmail);
        self::assertSame('confirmed', $response->status);
        self::assertCount(1, $response->items);
        self::assertSame(5, $response->items[0]->quantity);
    }

    public function test_place_order_throws_when_product_not_found(): void
    {
        $this->productRepository
            ->method('findByUuid')
            ->willReturn(null);

        $this->expectException(\App\Module\Product\Domain\Exception\ProductNotFoundException::class);

        $command = new PlaceOrderCommand(
            customerEmail: 'buyer@example.com',
            items: [['productUuid' => 'missing-uuid', 'quantity' => 1]],
        );

        $this->handler->handle($command);
    }

    public function test_place_order_throws_when_product_is_inactive(): void
    {
        $product = $this->makeProduct('prod-uuid-1');
        $product->deactivate();

        $this->productRepository
            ->method('findByUuid')
            ->willReturn($product);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not available for ordering');

        $command = new PlaceOrderCommand(
            customerEmail: 'buyer@example.com',
            items: [['productUuid' => 'prod-uuid-1', 'quantity' => 1]],
        );

        $this->handler->handle($command);
    }

    public function test_place_order_throws_insufficient_stock(): void
    {
        $productUuid   = 'prod-uuid-1';
        $product       = $this->makeProduct($productUuid);
        $inventoryItem = $this->makeInventoryItem($productUuid, 3); // only 3 available

        $this->productRepository
            ->method('findByUuid')
            ->willReturn($product);

        $this->inventoryRepository
            ->method('findByProductUuid')
            ->willReturn($inventoryItem);

        $this->expectException(InsufficientStockException::class);

        $command = new PlaceOrderCommand(
            customerEmail: 'buyer@example.com',
            items: [['productUuid' => $productUuid, 'quantity' => 10]], // wants 10, only 3 available
        );

        $this->handler->handle($command);
    }

    public function test_place_order_calculates_correct_total(): void
    {
        $productUuid   = 'prod-uuid-1';
        $product       = $this->makeProduct($productUuid); // price = 29.99
        $inventoryItem = $this->makeInventoryItem($productUuid, 100);

        $this->productRepository->method('findByUuid')->willReturn($product);
        $this->inventoryRepository->method('findByProductUuid')->willReturn($inventoryItem);
        $this->orderRepository->method('save');
        $this->inventoryRepository->method('save');

        $command = new PlaceOrderCommand(
            customerEmail: 'buyer@example.com',
            items: [['productUuid' => $productUuid, 'quantity' => 3]],
        );

        $response = $this->handler->handle($command);

        // 3 × 29.99 = 89.97
        self::assertEqualsWithDelta(89.97, $response->totalAmount, 0.01);
    }
}
