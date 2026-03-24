<?php

declare(strict_types=1);

namespace App\Module\Inventory\Application\Query;

use App\Module\Inventory\Domain\Exception\InventoryItemNotFoundException;
use App\Module\Inventory\Domain\InventoryRepositoryInterface;

final class GetInventoryQueryHandler
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventoryRepository,
    ) {
    }

    public function handle(string $productUuid): InventoryResponse
    {
        $item = $this->inventoryRepository->findByProductUuid($productUuid);

        if ($item === null) {
            throw new InventoryItemNotFoundException($productUuid);
        }

        return InventoryResponse::fromEntity($item);
    }
}
