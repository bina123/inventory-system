<?php

declare(strict_types=1);

namespace App\Module\Product\Application\Command;

final class DeleteProductCommand
{
    public function __construct(
        public readonly string $productUuid,
    ) {
    }
}
