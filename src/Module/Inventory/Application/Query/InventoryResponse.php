<?php

declare(strict_types=1);

namespace App\Module\Inventory\Application\Query;

use App\Module\Inventory\Domain\InventoryItem;

final class InventoryResponse implements \JsonSerializable
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $productUuid,
        public readonly string $productSku,
        public readonly string $productName,
        public readonly int $quantityOnHand,
        public readonly int $quantityReserved,
        public readonly int $quantityAvailable,
        public readonly int $lowStockThreshold,
        public readonly bool $isLowStock,
        public readonly string $updatedAt,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'uuid'                => $this->uuid,
            'product_uuid'        => $this->productUuid,
            'product_sku'         => $this->productSku,
            'product_name'        => $this->productName,
            'quantity_on_hand'    => $this->quantityOnHand,
            'quantity_reserved'   => $this->quantityReserved,
            'quantity_available'  => $this->quantityAvailable,
            'low_stock_threshold' => $this->lowStockThreshold,
            'is_low_stock'        => $this->isLowStock,
            'updated_at'          => $this->updatedAt,
        ];
    }

    public static function fromEntity(InventoryItem $item): self
    {
        return new self(
            uuid:              $item->getUuid(),
            productUuid:       $item->getProductUuid(),
            productSku:        $item->getProductSku(),
            productName:       $item->getProductName(),
            quantityOnHand:    $item->getQuantityOnHand(),
            quantityReserved:  $item->getQuantityReserved(),
            quantityAvailable: $item->getAvailableQuantity(),
            lowStockThreshold: $item->getLowStockThreshold(),
            isLowStock:        $item->isLowStock(),
            updatedAt:         $item->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
