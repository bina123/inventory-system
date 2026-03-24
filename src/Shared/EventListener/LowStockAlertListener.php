<?php

declare(strict_types=1);

namespace App\Shared\EventListener;

use App\Module\Inventory\Domain\Event\LowStockAlertEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Handles low-stock alert events.
 *
 * Current implementation: structured warning log.
 * Extension point: inject a MailerInterface or notification service
 * to send email/Slack alerts to operations staff without changing this class.
 */
#[AsEventListener(event: LowStockAlertEvent::class)]
final class LowStockAlertListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(LowStockAlertEvent $event): void
    {
        $this->logger->warning('LOW STOCK ALERT', [
            'product_uuid'    => $event->productUuid,
            'product_sku'     => $event->productSku,
            'product_name'    => $event->productName,
            'current_qty'     => $event->currentQuantity,
            'threshold'       => $event->threshold,
            'occurred_at'     => $event->occurredAt->format(\DateTimeInterface::ATOM),
        ]);

        // TODO: Send email/Slack notification to operations team
        // $this->mailer->send($this->buildLowStockEmail($event));
    }
}
