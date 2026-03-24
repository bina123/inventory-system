<?php

declare(strict_types=1);

namespace App\Module\Order\Application\Command;

use App\Module\Inventory\Domain\InventoryRepositoryInterface;
use App\Module\Order\Application\Query\OrderResponse;
use App\Module\Order\Domain\Exception\OrderNotFoundException;
use App\Module\Order\Domain\OrderRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class FulfilOrderCommandHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InventoryRepositoryInterface $inventoryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(FulfilOrderCommand $command): OrderResponse
    {
        $order = $this->orderRepository->findByUuid($command->orderUuid);

        if ($order === null) {
            throw new OrderNotFoundException($command->orderUuid);
        }

        // May need to transition through PROCESSING first if still CONFIRMED
        if ($order->getStatus()->value === 'confirmed') {
            $order->startProcessing();
        }

        // fulfil() enforces state machine: PROCESSING → FULFILLED
        $order->fulfil();

        // Commit reserved stock → decrement onHand
        foreach ($order->getItems() as $lineItem) {
            $inventoryItem = $this->inventoryRepository->findByProductUuid($lineItem->getProductUuid());

            if ($inventoryItem !== null) {
                $inventoryItem->commitReserved($lineItem->getQuantity());
                $this->inventoryRepository->save($inventoryItem);

                foreach ($inventoryItem->pullDomainEvents() as $event) {
                    $this->eventDispatcher->dispatch($event);
                }
            }
        }

        $this->orderRepository->save($order, flush: true);

        foreach ($order->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return OrderResponse::fromEntity($order);
    }
}
