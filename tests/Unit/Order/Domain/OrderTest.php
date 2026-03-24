<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain;

use App\Module\Order\Domain\Event\OrderCancelledEvent;
use App\Module\Order\Domain\Event\OrderFulfilledEvent;
use App\Module\Order\Domain\Event\OrderPlacedEvent;
use App\Module\Order\Domain\Exception\InvalidOrderTransitionException;
use App\Module\Order\Domain\Order;
use App\Module\Order\Domain\ValueObject\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    private function makeOrder(): Order
    {
        return new Order('customer@example.com', 'Please handle with care.');
    }

    private function addItemToOrder(Order $order): void
    {
        $order->addItem(
            productUuid:       'uuid-product-1',
            productSku:        'WGT-001',
            productName:       'Widget Pro',
            quantity:          2,
            unitPriceAmount:   2999,
            unitPriceCurrency: 'USD',
        );
    }

    public function test_new_order_is_pending(): void
    {
        $order = $this->makeOrder();

        self::assertSame(OrderStatus::PENDING, $order->getStatus());
        self::assertSame(0, $order->getTotalAmount());
        self::assertEmpty($order->getItems());
    }

    public function test_adding_item_updates_total(): void
    {
        $order = $this->makeOrder();
        $this->addItemToOrder($order);

        // 2 × 2999 = 5998 cents
        self::assertSame(5998, $order->getTotalAmount());
        self::assertCount(1, $order->getItems());
    }

    public function test_confirm_transitions_to_confirmed_and_records_event(): void
    {
        $order = $this->makeOrder();
        $this->addItemToOrder($order);

        $order->confirm();

        self::assertSame(OrderStatus::CONFIRMED, $order->getStatus());

        $events = $order->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderPlacedEvent::class, $events[0]);
        self::assertSame('customer@example.com', $events[0]->customerEmail);
    }

    public function test_cancel_from_pending_records_cancelled_event(): void
    {
        $order = $this->makeOrder();
        $this->addItemToOrder($order);

        $order->cancel();

        self::assertSame(OrderStatus::CANCELLED, $order->getStatus());

        $events = $order->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderCancelledEvent::class, $events[0]);
    }

    public function test_cancel_from_confirmed_is_allowed(): void
    {
        $order = $this->makeOrder();
        $this->addItemToOrder($order);
        $order->confirm();
        $order->pullDomainEvents();

        $order->cancel();

        self::assertSame(OrderStatus::CANCELLED, $order->getStatus());
    }

    public function test_fulfil_after_processing_records_fulfilled_event(): void
    {
        $order = $this->makeOrder();
        $this->addItemToOrder($order);
        $order->confirm();
        $order->startProcessing();
        $order->pullDomainEvents();

        $order->fulfil();

        self::assertSame(OrderStatus::FULFILLED, $order->getStatus());

        $events = $order->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderFulfilledEvent::class, $events[0]);
    }

    public function test_cannot_cancel_fulfilled_order(): void
    {
        $order = $this->makeOrder();
        $this->addItemToOrder($order);
        $order->confirm();
        $order->startProcessing();
        $order->fulfil();

        $this->expectException(InvalidOrderTransitionException::class);

        $order->cancel();
    }

    public function test_cannot_fulfil_cancelled_order(): void
    {
        $order = $this->makeOrder();
        $this->addItemToOrder($order);
        $order->cancel();

        $this->expectException(InvalidOrderTransitionException::class);

        $order->fulfil();
    }

    public function test_cannot_add_item_to_non_pending_order(): void
    {
        $order = $this->makeOrder();
        $this->addItemToOrder($order);
        $order->confirm();

        $this->expectException(\LogicException::class);

        $this->addItemToOrder($order);
    }

    public function test_order_status_state_machine(): void
    {
        self::assertTrue(OrderStatus::PENDING->canTransitionTo(OrderStatus::CONFIRMED));
        self::assertTrue(OrderStatus::PENDING->canTransitionTo(OrderStatus::CANCELLED));
        self::assertFalse(OrderStatus::PENDING->canTransitionTo(OrderStatus::FULFILLED));

        self::assertTrue(OrderStatus::CONFIRMED->canTransitionTo(OrderStatus::PROCESSING));
        self::assertTrue(OrderStatus::CONFIRMED->canTransitionTo(OrderStatus::CANCELLED));
        self::assertFalse(OrderStatus::CONFIRMED->canTransitionTo(OrderStatus::FULFILLED));

        self::assertTrue(OrderStatus::PROCESSING->canTransitionTo(OrderStatus::FULFILLED));
        self::assertTrue(OrderStatus::PROCESSING->canTransitionTo(OrderStatus::CANCELLED));

        self::assertFalse(OrderStatus::FULFILLED->canTransitionTo(OrderStatus::CANCELLED));
        self::assertFalse(OrderStatus::CANCELLED->canTransitionTo(OrderStatus::PENDING));
    }

    public function test_fulfilled_and_cancelled_are_terminal(): void
    {
        self::assertTrue(OrderStatus::FULFILLED->isTerminal());
        self::assertTrue(OrderStatus::CANCELLED->isTerminal());
        self::assertFalse(OrderStatus::PENDING->isTerminal());
        self::assertFalse(OrderStatus::CONFIRMED->isTerminal());
    }

    public function test_line_items_are_captured_in_order_placed_event(): void
    {
        $order = $this->makeOrder();
        $this->addItemToOrder($order);

        $order->confirm();

        $events = $order->pullDomainEvents();
        $event  = $events[0];

        self::assertInstanceOf(OrderPlacedEvent::class, $event);
        self::assertCount(1, $event->lineItems);
        self::assertSame('uuid-product-1', $event->lineItems[0]['productUuid']);
        self::assertSame('WGT-001', $event->lineItems[0]['productSku']);
        self::assertSame(2, $event->lineItems[0]['quantity']);
    }
}
