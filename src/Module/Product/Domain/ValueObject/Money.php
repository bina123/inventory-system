<?php

declare(strict_types=1);

namespace App\Module\Product\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

/**
 * Value Object representing a monetary amount.
 * Stored as integer cents to avoid floating-point precision issues.
 */
#[ORM\Embeddable]
final class Money
{
    #[ORM\Column(type: 'integer', name: 'price_amount')]
    private int $amount;

    #[ORM\Column(type: 'string', length: 3, name: 'price_currency')]
    private string $currency;

    public function __construct(int $amount, string $currency = 'USD')
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Money amount cannot be negative.');
        }

        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO 4217 code.');
        }

        $this->amount   = $amount;
        $this->currency = strtoupper($currency);
    }

    public static function fromFloat(float $amount, string $currency = 'USD'): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function toFloat(): float
    {
        return $this->amount / 100;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \LogicException(
                sprintf('Cannot operate on different currencies: %s and %s.', $this->currency, $other->currency),
            );
        }
    }
}
