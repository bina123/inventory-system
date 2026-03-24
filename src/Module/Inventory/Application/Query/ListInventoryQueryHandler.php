<?php

declare(strict_types=1);

namespace App\Module\Inventory\Application\Query;

use App\Module\Inventory\Domain\InventoryRepositoryInterface;

final class ListInventoryQueryHandler
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventoryRepository,
    ) {
    }

    /**
     * @return array{data: list<InventoryResponse>, meta: array{page: int, limit: int, total: int}}
     */
    public function handle(int $page = 1, int $limit = 25): array
    {
        $items = $this->inventoryRepository->findAll($page, $limit);
        $total = $this->inventoryRepository->countAll();

        return [
            'data' => array_map(InventoryResponse::fromEntity(...), $items),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total],
        ];
    }

    /**
     * @return list<InventoryResponse>
     */
    public function handleLowStock(): array
    {
        return array_map(
            InventoryResponse::fromEntity(...),
            $this->inventoryRepository->findLowStockItems(),
        );
    }
}
