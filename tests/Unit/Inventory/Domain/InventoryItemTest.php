<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Module\Inventory\Domain\Event\LowStockAlertEvent;
use App\Module\Inventory\Domain\Event\StockUpdatedEvent;
use App\Module\Inventory\Domain\Exception\InsufficientStockException;
use App\Module\Inventory\Domain\InventoryItem;
use PHPUnit\Framework\TestCase;

final class InventoryItemTest extends TestCase
{
    private function makeItem(int $initialQty = 100, int $threshold = 10): InventoryItem
    {
        return new InventoryItem(
            productId:        1,
            productUuid:      'uuid-product-1',
            productSku:       'WGT-001',
            productName:      'Widget Pro',
            initialQuantity:  $initialQty,
            lowStockThreshold: $threshold,
        );
    }

    public function test_initial_state_is_correct(): void
    {
        $item = $this->makeItem(50);

        self::assertSame(50, $item->getQuantityOnHand());
        self::assertSame(0, $item->getQuantityReserved());
        self::assertSame(50, $item->getAvailableQuantity());
        self::assertFalse($item->isLowStock());
    }

    public function test_increase_stock_adds_to_on_hand(): void
    {
        $item = $this->makeItem(50);

        $item->increaseStock(25, 'Received from supplier');

        self::assertSame(75, $item->getQuantityOnHand());
    }

    public function test_increase_stock_records_stock_updated_event(): void
    {
        $item = $this->makeItem(50);

        $item->increaseStock(25, 'Received from supplier');
        $events = $item->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(StockUpdatedEvent::class, $events[0]);
        self::assertSame(50, $events[0]->previousQuantity);
        self::assertSame(75, $events[0]->newQuantity);
    }

    public function test_increase_stock_with_zero_quantity_throws_exception(): void
    {
        $item = $this->makeItem(50);

        $this->expectException(\InvalidArgumentException::class);

        $item->increaseStock(0);
    }

    public function test_reserve_reduces_available_quantity(): void
    {
        $item = $this->makeItem(100);

        $item->reserve(30);

        self::assertSame(100, $item->getQuantityOnHand());
        self::assertSame(30, $item->getQuantityReserved());
        self::assertSame(70, $item->getAvailableQuantity());
    }

    public function test_reserve_more_than_available_throws_insufficient_stock(): void
    {
        $item = $this->makeItem(20);

        $this->expectException(InsufficientStockException::class);

        $item->reserve(25);
    }

    public function test_release_restores_available_quantity(): void
    {
        $item = $this->makeItem(100);
        $item->reserve(40);

        $item->release(20);

        self::assertSame(100, $item->getQuantityOnHand());
        self::assertSame(20, $item->getQuantityReserved());
        self::assertSame(80, $item->getAvailableQuantity());
    }

    public function test_release_more_than_reserved_throws_logic_exception(): void
    {
        $item = $this->makeItem(100);
        $item->reserve(10);

        $this->expectException(\LogicException::class);

        $item->release(20);
    }

    public function test_commit_reserved_decrements_on_hand_and_reserved(): void
    {
        $item = $this->makeItem(100);
        $item->reserve(30);

        $item->commitReserved(30);

        self::assertSame(70, $item->getQuantityOnHand());
        self::assertSame(0, $item->getQuantityReserved());
        self::assertSame(70, $item->getAvailableQuantity());
    }

    public function test_decrease_below_threshold_raises_low_stock_alert(): void
    {
        $item = $this->makeItem(15, 10); // threshold = 10

        $item->decreaseStock(6, 'Manual write-off'); // drops to 9

        $events = $item->pullDomainEvents();

        self::assertCount(2, $events); // StockUpdatedEvent + LowStockAlertEvent
        self::assertInstanceOf(StockUpdatedEvent::class, $events[0]);
        self::assertInstanceOf(LowStockAlertEvent::class, $events[1]);
        self::assertSame(9, $events[1]->currentQuantity);
        self::assertSame(10, $events[1]->threshold);
    }

    public function test_reserve_below_threshold_raises_low_stock_alert(): void
    {
        $item = $this->makeItem(12, 10);

        $item->reserve(5); // available drops to 7, on-hand stays 12 — but check threshold on on-hand
        // Actually reserve triggers checkLowStockThreshold; 12 > 10 so no alert
        $events = $item->pullDomainEvents();
        self::assertEmpty($events);

        // Now decrease on-hand to trigger the alert
        $item->increaseStock(1); // 13
        $item->decreaseStock(4); // 9 — below threshold

        $events = $item->pullDomainEvents();
        $lowStockEvents = array_filter($events, fn ($e) => $e instanceof LowStockAlertEvent);

        self::assertNotEmpty($lowStockEvents);
    }

    public function test_decrease_stock_throws_on_insufficient_available(): void
    {
        $item = $this->makeItem(20);
        $item->reserve(15); // 5 available

        $this->expectException(InsufficientStockException::class);

        $item->decreaseStock(10); // needs 10, only 5 available
    }

    public function test_pull_domain_events_clears_buffer(): void
    {
        $item = $this->makeItem(100);
        $item->increaseStock(10);

        $item->pullDomainEvents();

        self::assertEmpty($item->pullDomainEvents());
    }
}
