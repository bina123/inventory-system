<?php

declare(strict_types=1);

namespace App\Module\Inventory\Domain\Event;

final class LowStockAlertEvent
{
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $productUuid,
        public readonly string $productSku,
        public readonly string $productName,
        public readonly int $currentQuantity,
        public readonly int $threshold,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
