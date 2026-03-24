<?php

declare(strict_types=1);

namespace App\Module\Inventory\Domain;

interface InventoryRepositoryInterface
{
    public function findByProductId(int $productId): ?InventoryItem;

    public function findByProductUuid(string $productUuid): ?InventoryItem;

    public function findByUuid(string $uuid): ?InventoryItem;

    /** @return list<InventoryItem> */
    public function findAll(int $page, int $limit): array;

    /** @return list<InventoryItem> Items with quantity <= their threshold */
    public function findLowStockItems(): array;

    public function countAll(): int;

    public function save(InventoryItem $item, bool $flush = false): void;
}
