<?php

declare(strict_types=1);

namespace App\Module\Order\Application\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class PlaceOrderCommand
{
    /**
     * @param list<array{productUuid: string, quantity: int}> $items
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $customerEmail,

        #[Assert\NotNull]
        #[Assert\Count(min: 1, minMessage: 'An order must contain at least one item.')]
        #[Assert\Valid]
        public readonly array $items,

        public readonly ?string $notes = null,
    ) {
    }
}
