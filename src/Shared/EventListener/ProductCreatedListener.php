<?php

declare(strict_types=1);

namespace App\Shared\EventListener;

use App\Module\Product\Domain\Event\ProductCreatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ProductCreatedEvent::class)]
final class ProductCreatedListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProductCreatedEvent $event): void
    {
        $this->logger->info('Product created', [
            'product_uuid' => $event->productUuid,
            'sku'          => $event->sku,
            'name'         => $event->name,
            'occurred_at'  => $event->occurredAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
