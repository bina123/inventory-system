<?php

declare(strict_types=1);

namespace App\Module\Product\Domain\ValueObject;

/**
 * Value Object representing a Stock Keeping Unit code.
 * Format: 3-50 alphanumeric characters and hyphens, uppercase.
 */
final class Sku
{
    private const PATTERN = '/^[A-Z0-9][A-Z0-9\-]{1,48}[A-Z0-9]$/';

    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if (!preg_match(self::PATTERN, $normalized)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid SKU format: "%s". Must be 3-50 uppercase alphanumeric chars/hyphens.', $value),
            );
        }

        $this->value = $normalized;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
