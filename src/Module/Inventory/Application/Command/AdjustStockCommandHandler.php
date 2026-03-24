<?php

declare(strict_types=1);

namespace App\Module\Inventory\Application\Command;

use App\Module\Inventory\Application\Query\InventoryResponse;
use App\Module\Inventory\Domain\Exception\InventoryItemNotFoundException;
use App\Module\Inventory\Domain\InventoryRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class AdjustStockCommandHandler
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventoryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(AdjustStockCommand $command): InventoryResponse
    {
        $item = $this->inventoryRepository->findByProductUuid($command->productUuid);

        if ($item === null) {
            throw new InventoryItemNotFoundException($command->productUuid);
        }

        if ($command->quantity > 0) {
            $item->increaseStock($command->quantity, $command->reason);
        } else {
            $item->decreaseStock(abs($command->quantity), $command->reason);
        }

        $this->inventoryRepository->save($item, flush: true);

        foreach ($item->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return InventoryResponse::fromEntity($item);
    }
}
