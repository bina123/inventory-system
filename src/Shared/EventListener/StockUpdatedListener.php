<?php

declare(strict_types=1);

namespace App\Shared\EventListener;

use App\Module\Inventory\Domain\Event\StockUpdatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: StockUpdatedEvent::class)]
final class StockUpdatedListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(StockUpdatedEvent $event): void
    {
        $this->logger->info('Stock updated', [
            'product_uuid'     => $event->productUuid,
            'product_sku'      => $event->productSku,
            'previous_qty'     => $event->previousQuantity,
            'new_qty'          => $event->newQuantity,
            'delta'            => $event->newQuantity - $event->previousQuantity,
            'reason'           => $event->reason,
            'occurred_at'      => $event->occurredAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
