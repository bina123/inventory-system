<?php

declare(strict_types=1);

namespace App\Module\Product\Domain\Event;

final class ProductCreatedEvent
{
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $productUuid,
        public readonly string $sku,
        public readonly string $name,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
