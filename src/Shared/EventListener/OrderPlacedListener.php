<?php

declare(strict_types=1);

namespace App\Shared\EventListener;

use App\Module\Order\Domain\Event\OrderPlacedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Handles order-placed events.
 *
 * Current implementation: structured audit log.
 * Extension point: inject a MailerInterface to send order confirmation emails.
 */
#[AsEventListener(event: OrderPlacedEvent::class)]
final class OrderPlacedListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrderPlacedEvent $event): void
    {
        $this->logger->info('Order placed', [
            'order_uuid'     => $event->orderUuid,
            'customer_email' => $event->customerEmail,
            'line_items'     => $event->lineItems,
            'occurred_at'    => $event->occurredAt->format(\DateTimeInterface::ATOM),
        ]);

        // TODO: Send order confirmation email to customer
        // $this->mailer->send($this->buildConfirmationEmail($event));
    }
}
