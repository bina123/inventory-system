<?php

declare(strict_types=1);

namespace App\Module\Inventory\Application\Command;

final class ReleaseStockCommand
{
    public function __construct(
        public readonly string $productUuid,
        public readonly int $quantity,
    ) {
    }
}
