<?php

declare(strict_types=1);

namespace App\Module\Order\Application\Command;

use App\Module\Inventory\Domain\InventoryRepositoryInterface;
use App\Module\Order\Application\Query\OrderResponse;
use App\Module\Order\Domain\Exception\OrderNotFoundException;
use App\Module\Order\Domain\OrderRepositoryInterface;
use App\Module\Order\Domain\ValueObject\OrderStatus;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class CancelOrderCommandHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InventoryRepositoryInterface $inventoryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(CancelOrderCommand $command): OrderResponse
    {
        $order = $this->orderRepository->findByUuid($command->orderUuid);

        if ($order === null) {
            throw new OrderNotFoundException($command->orderUuid);
        }

        // Capture status before cancellation to know whether inventory was reserved.
        // Only CONFIRMED and PROCESSING orders have reserved inventory.
        $hadReservedInventory = in_array(
            $order->getStatus(),
            [OrderStatus::CONFIRMED, OrderStatus::PROCESSING],
            strict: true,
        );

        // cancel() enforces the state machine — throws InvalidOrderTransitionException
        // if the order is already FULFILLED or CANCELLED
        $order->cancel();

        // Release reserved stock only if the order previously had inventory reserved
        if (!$hadReservedInventory) {
            $this->orderRepository->save($order, flush: true);

            foreach ($order->pullDomainEvents() as $event) {
                $this->eventDispatcher->dispatch($event);
            }

            return OrderResponse::fromEntity($order);
        }

        foreach ($order->getItems() as $lineItem) {
            $inventoryItem = $this->inventoryRepository->findByProductUuid($lineItem->getProductUuid());

            if ($inventoryItem !== null) {
                $inventoryItem->release($lineItem->getQuantity());
                $this->inventoryRepository->save($inventoryItem);
            }
        }

        $this->orderRepository->save($order, flush: true);

        foreach ($order->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return OrderResponse::fromEntity($order);
    }
}
