<?php

declare(strict_types=1);

namespace App\Module\Inventory\Application\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class AdjustStockCommand
{
    public function __construct(
        public readonly string $productUuid,

        /** Positive = increase, Negative = decrease */
        #[Assert\NotEqualTo(0, message: 'Adjustment quantity cannot be zero.')]
        public readonly int $quantity,

        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        public readonly string $reason,
    ) {
    }
}
