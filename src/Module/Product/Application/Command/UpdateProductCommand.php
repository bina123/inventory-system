<?php

declare(strict_types=1);

namespace App\Module\Product\Application\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateProductCommand
{
    public function __construct(
        public readonly string $productUuid,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 200)]
        public readonly string $name,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public readonly float $price,

        #[Assert\NotBlank]
        #[Assert\Length(exactly: 3)]
        public readonly string $currency,

        #[Assert\NotBlank]
        public readonly string $categoryUuid,

        public readonly ?string $description = null,
    ) {
    }
}
