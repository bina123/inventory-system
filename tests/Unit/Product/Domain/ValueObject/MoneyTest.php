<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Domain\ValueObject;

use App\Module\Product\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_from_float_converts_to_cents(): void
    {
        $money = Money::fromFloat(29.99);

        self::assertSame(2999, $money->getAmount());
        self::assertSame('USD', $money->getCurrency());
    }

    public function test_to_float_converts_from_cents(): void
    {
        $money = new Money(2999, 'USD');

        self::assertEqualsWithDelta(29.99, $money->toFloat(), 0.001);
    }

    public function test_negative_amount_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        new Money(-1, 'USD');
    }

    public function test_invalid_currency_code_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ISO 4217');

        new Money(100, 'US');
    }

    public function test_currency_is_normalized_to_uppercase(): void
    {
        $money = new Money(100, 'usd');

        self::assertSame('USD', $money->getCurrency());
    }

    public function test_add_same_currency(): void
    {
        $a = new Money(1000, 'USD');
        $b = new Money(500, 'USD');

        $result = $a->add($b);

        self::assertSame(1500, $result->getAmount());
        self::assertSame('USD', $result->getCurrency());
    }

    public function test_add_different_currency_throws_exception(): void
    {
        $a = new Money(1000, 'USD');
        $b = new Money(500, 'EUR');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('different currencies');

        $a->add($b);
    }

    public function test_multiply(): void
    {
        $money  = new Money(1000, 'USD');
        $result = $money->multiply(3);

        self::assertSame(3000, $result->getAmount());
    }

    public function test_equals_same_amount_and_currency(): void
    {
        $a = new Money(1000, 'USD');
        $b = new Money(1000, 'USD');

        self::assertTrue($a->equals($b));
    }

    public function test_equals_different_amounts(): void
    {
        $a = new Money(1000, 'USD');
        $b = new Money(2000, 'USD');

        self::assertFalse($a->equals($b));
    }
}
