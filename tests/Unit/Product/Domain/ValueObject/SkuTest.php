<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Domain\ValueObject;

use App\Module\Product\Domain\ValueObject\Sku;
use PHPUnit\Framework\TestCase;

final class SkuTest extends TestCase
{
    public function test_valid_sku_is_accepted(): void
    {
        $sku = new Sku('WGT-PRO-001');

        self::assertSame('WGT-PRO-001', $sku->getValue());
    }

    public function test_sku_is_normalized_to_uppercase(): void
    {
        $sku = new Sku('wgt-pro-001');

        self::assertSame('WGT-PRO-001', $sku->getValue());
    }

    public function test_sku_is_trimmed(): void
    {
        $sku = new Sku('  WGT001  ');

        self::assertSame('WGT001', $sku->getValue());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSkuProvider')]
    public function test_invalid_sku_throws_exception(string $invalidSku): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Sku($invalidSku);
    }

    /** @return array<string, array{string}> */
    public static function invalidSkuProvider(): array
    {
        return [
            'too short'              => ['AB'],
            'starts with hyphen'     => ['-ABC'],
            'ends with hyphen'       => ['ABC-'],
            'special chars'          => ['WGT@001'],
            'empty string'           => [''],
        ];
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $a = new Sku('WGT-001');
        $b = new Sku('wgt-001');

        self::assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $a = new Sku('WGT-001');
        $b = new Sku('WGT-002');

        self::assertFalse($a->equals($b));
    }

    public function test_to_string_returns_value(): void
    {
        $sku = new Sku('WGT-001');

        self::assertSame('WGT-001', (string) $sku);
    }
}
