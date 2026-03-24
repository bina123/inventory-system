<?php

declare(strict_types=1);

namespace App\Module\Inventory\Domain\Event;

final class StockUpdatedEvent
{
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $productUuid,
        public readonly string $productSku,
        public readonly int $previousQuantity,
        public readonly int $newQuantity,
        public readonly string $reason,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
