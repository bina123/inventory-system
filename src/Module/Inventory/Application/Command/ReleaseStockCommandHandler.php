<?php

declare(strict_types=1);

namespace App\Module\Inventory\Application\Command;

use App\Module\Inventory\Domain\Exception\InventoryItemNotFoundException;
use App\Module\Inventory\Domain\InventoryRepositoryInterface;

final class ReleaseStockCommandHandler
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventoryRepository,
    ) {
    }

    public function handle(ReleaseStockCommand $command): void
    {
        $item = $this->inventoryRepository->findByProductUuid($command->productUuid);

        if ($item === null) {
            throw new InventoryItemNotFoundException($command->productUuid);
        }

        $item->release($command->quantity);
        $this->inventoryRepository->save($item, flush: true);
    }
}
