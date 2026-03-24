<?php

declare(strict_types=1);

namespace App\Module\Order\Domain\ValueObject;

/**
 * Order lifecycle state machine.
 *
 * Valid transitions:
 *   PENDING   → CONFIRMED | CANCELLED
 *   CONFIRMED → PROCESSING | CANCELLED
 *   PROCESSING→ FULFILLED | CANCELLED
 *   FULFILLED → (terminal)
 *   CANCELLED → (terminal)
 */
enum OrderStatus: string
{
    case PENDING    = 'pending';
    case CONFIRMED  = 'confirmed';
    case PROCESSING = 'processing';
    case FULFILLED  = 'fulfilled';
    case CANCELLED  = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PENDING    => in_array($next, [self::CONFIRMED, self::CANCELLED], strict: true),
            self::CONFIRMED  => in_array($next, [self::PROCESSING, self::CANCELLED], strict: true),
            self::PROCESSING => in_array($next, [self::FULFILLED, self::CANCELLED], strict: true),
            self::FULFILLED,
            self::CANCELLED  => false,
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::FULFILLED, self::CANCELLED => true,
            default                          => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING    => 'Pending',
            self::CONFIRMED  => 'Confirmed',
            self::PROCESSING => 'Processing',
            self::FULFILLED  => 'Fulfilled',
            self::CANCELLED  => 'Cancelled',
        };
    }
}
