<?php

declare(strict_types=1);

namespace App\Module\Order\Domain\Event;

final class OrderFulfilledEvent
{
    public readonly \DateTimeImmutable $occurredAt;

    /**
     * @param list<array{productUuid: string, productSku: string, quantity: int}> $lineItems
     */
    public function __construct(
        public readonly string $orderUuid,
        public readonly string $customerEmail,
        public readonly array $lineItems,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
