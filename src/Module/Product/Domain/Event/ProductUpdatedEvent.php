<?php

declare(strict_types=1);

namespace App\Module\Product\Domain\Event;

final class ProductUpdatedEvent
{
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $productUuid,
        public readonly string $sku,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
