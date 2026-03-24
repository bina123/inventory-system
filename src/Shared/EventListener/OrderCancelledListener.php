<?php

declare(strict_types=1);

namespace App\Shared\EventListener;

use App\Module\Order\Domain\Event\OrderCancelledEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrderCancelledEvent::class)]
final class OrderCancelledListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrderCancelledEvent $event): void
    {
        $this->logger->info('Order cancelled — reserved stock released', [
            'order_uuid'     => $event->orderUuid,
            'customer_email' => $event->customerEmail,
            'line_items'     => $event->lineItems,
            'occurred_at'    => $event->occurredAt->format(\DateTimeInterface::ATOM),
        ]);

        // TODO: Send cancellation notification to customer
    }
}
