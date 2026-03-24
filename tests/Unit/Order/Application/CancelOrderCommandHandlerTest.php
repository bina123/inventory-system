<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application;

use App\Module\Inventory\Domain\InventoryItem;
use App\Module\Inventory\Domain\InventoryRepositoryInterface;
use App\Module\Order\Application\Command\CancelOrderCommand;
use App\Module\Order\Application\Command\CancelOrderCommandHandler;
use App\Module\Order\Domain\Exception\InvalidOrderTransitionException;
use App\Module\Order\Domain\Exception\OrderNotFoundException;
use App\Module\Order\Domain\Order;
use App\Module\Order\Domain\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class CancelOrderCommandHandlerTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $orderRepository;
    private InventoryRepositoryInterface&MockObject $inventoryRepository;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private CancelOrderCommandHandler $handler;

    protected function setUp(): void
    {
        $this->orderRepository     = $this->createMock(OrderRepositoryInterface::class);
        $this->inventoryRepository = $this->createMock(InventoryRepositoryInterface::class);
        $this->eventDispatcher     = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new CancelOrderCommandHandler(
            orderRepository:     $this->orderRepository,
            inventoryRepository: $this->inventoryRepository,
            eventDispatcher:     $this->eventDispatcher,
        );
    }

    private function makePendingOrder(): Order
    {
        $order = new Order('customer@example.com');
        $order->addItem(
            productUuid:       'uuid-product-1',
            productSku:        'WGT-001',
            productName:       'Widget Pro',
            quantity:          5,
            unitPriceAmount:   2999,
            unitPriceCurrency: 'USD',
        );

        return $order;
    }

    public function test_cancel_pending_order_succeeds(): void
    {
        $order = $this->makePendingOrder();

        $inventoryItem = new InventoryItem(
            productId:       1,
            productUuid:     'uuid-product-1',
            productSku:      'WGT-001',
            productName:     'Widget Pro',
            initialQuantity: 100,
        );

        $this->orderRepository
            ->method('findByUuid')
            ->with('order-uuid-1')
            ->willReturn($order);

        $this->inventoryRepository
            ->method('findByProductUuid')
            ->with('uuid-product-1')
            ->willReturn($inventoryItem);

        $this->orderRepository->expects(self::once())->method('save');

        $response = $this->handler->handle(new CancelOrderCommand('order-uuid-1'));

        self::assertSame('cancelled', $response->status);
    }

    public function test_cancel_non_existent_order_throws_exception(): void
    {
        $this->orderRepository
            ->method('findByUuid')
            ->willReturn(null);

        $this->expectException(OrderNotFoundException::class);

        $this->handler->handle(new CancelOrderCommand('missing-uuid'));
    }

    public function test_cancel_fulfilled_order_throws_invalid_transition(): void
    {
        $order = $this->makePendingOrder();
        $order->confirm();
        $order->startProcessing();
        $order->fulfil();
        $order->pullDomainEvents();

        $this->orderRepository
            ->method('findByUuid')
            ->willReturn($order);

        $this->expectException(InvalidOrderTransitionException::class);

        $this->handler->handle(new CancelOrderCommand('order-uuid-1'));
    }

    public function test_stock_is_released_when_order_cancelled(): void
    {
        $order = $this->makePendingOrder();
        $order->confirm();
        $order->pullDomainEvents();

        $inventoryItem = $this->createMock(InventoryItem::class);
        $inventoryItem->method('getProductUuid')->willReturn('uuid-product-1');
        $inventoryItem->expects(self::once())
            ->method('release')
            ->with(5); // quantity from the order item

        $this->orderRepository->method('findByUuid')->willReturn($order);
        $this->inventoryRepository->method('findByProductUuid')->willReturn($inventoryItem);

        $this->handler->handle(new CancelOrderCommand('order-uuid-1'));
    }
}
