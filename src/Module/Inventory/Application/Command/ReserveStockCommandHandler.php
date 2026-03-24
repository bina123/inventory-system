<?php

declare(strict_types=1);

namespace App\Module\Inventory\Application\Command;

use App\Module\Inventory\Domain\Exception\InventoryItemNotFoundException;
use App\Module\Inventory\Domain\InventoryRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ReserveStockCommandHandler
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventoryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(ReserveStockCommand $command): void
    {
        $item = $this->inventoryRepository->findByProductUuid($command->productUuid);

        if ($item === null) {
            throw new InventoryItemNotFoundException($command->productUuid);
        }

        $item->reserve($command->quantity);
        $this->inventoryRepository->save($item, flush: true);

        foreach ($item->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
