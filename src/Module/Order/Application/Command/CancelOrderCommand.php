<?php

declare(strict_types=1);

namespace App\Module\Order\Application\Command;

final class CancelOrderCommand
{
    public function __construct(
        public readonly string $orderUuid,
    ) {
    }
}
