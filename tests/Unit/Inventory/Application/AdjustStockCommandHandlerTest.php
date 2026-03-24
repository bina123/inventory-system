<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application;

use App\Module\Inventory\Application\Command\AdjustStockCommand;
use App\Module\Inventory\Application\Command\AdjustStockCommandHandler;
use App\Module\Inventory\Domain\Exception\InventoryItemNotFoundException;
use App\Module\Inventory\Domain\Exception\InsufficientStockException;
use App\Module\Inventory\Domain\InventoryItem;
use App\Module\Inventory\Domain\InventoryRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class AdjustStockCommandHandlerTest extends TestCase
{
    private InventoryRepositoryInterface&MockObject $inventoryRepository;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private AdjustStockCommandHandler $handler;

    protected function setUp(): void
    {
        $this->inventoryRepository = $this->createMock(InventoryRepositoryInterface::class);
        $this->eventDispatcher     = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new AdjustStockCommandHandler(
            inventoryRepository: $this->inventoryRepository,
            eventDispatcher:     $this->eventDispatcher,
        );
    }

    private function makeItem(int $qty = 50): InventoryItem
    {
        return new InventoryItem(
            productId:        1,
            productUuid:      'prod-uuid-1',
            productSku:       'WGT-001',
            productName:      'Widget Pro',
            initialQuantity:  $qty,
            lowStockThreshold: 10,
        );
    }

    public function test_positive_quantity_increases_stock(): void
    {
        $item = $this->makeItem(50);

        $this->inventoryRepository
            ->method('findByProductUuid')
            ->willReturn($item);

        $this->inventoryRepository->expects(self::once())->method('save');

        $command  = new AdjustStockCommand('prod-uuid-1', +20, 'New stock received');
        $response = $this->handler->handle($command);

        self::assertSame(70, $response->quantityOnHand);
    }

    public function test_negative_quantity_decreases_stock(): void
    {
        $item = $this->makeItem(50);

        $this->inventoryRepository
            ->method('findByProductUuid')
            ->willReturn($item);

        $command  = new AdjustStockCommand('prod-uuid-1', -10, 'Write-off damaged goods');
        $response = $this->handler->handle($command);

        self::assertSame(40, $response->quantityOnHand);
    }

    public function test_product_not_found_throws_exception(): void
    {
        $this->inventoryRepository
            ->method('findByProductUuid')
            ->willReturn(null);

        $this->expectException(InventoryItemNotFoundException::class);

        $this->handler->handle(new AdjustStockCommand('missing', -5, 'test'));
    }

    public function test_decrease_beyond_available_throws_insufficient_stock(): void
    {
        $item = $this->makeItem(10);

        $this->inventoryRepository
            ->method('findByProductUuid')
            ->willReturn($item);

        $this->expectException(InsufficientStockException::class);

        $this->handler->handle(new AdjustStockCommand('prod-uuid-1', -50, 'overstock decrease'));
    }

    public function test_events_are_dispatched_after_adjustment(): void
    {
        $item = $this->makeItem(50);

        $this->inventoryRepository
            ->method('findByProductUuid')
            ->willReturn($item);

        $this->eventDispatcher
            ->expects(self::atLeastOnce())
            ->method('dispatch');

        $this->handler->handle(new AdjustStockCommand('prod-uuid-1', +10, 'Stock received'));
    }
}
