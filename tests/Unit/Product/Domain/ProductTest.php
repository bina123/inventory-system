<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Domain;

use App\Module\Product\Domain\Category;
use App\Module\Product\Domain\Event\ProductCreatedEvent;
use App\Module\Product\Domain\Event\ProductUpdatedEvent;
use App\Module\Product\Domain\Product;
use App\Module\Product\Domain\ValueObject\Money;
use App\Module\Product\Domain\ValueObject\Sku;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    private Category $category;

    protected function setUp(): void
    {
        $this->category = new Category('Electronics', 'Electronic goods');
    }

    public function test_product_is_created_with_correct_defaults(): void
    {
        $product = new Product(
            name:     'Widget Pro',
            sku:      new Sku('WGT-PRO-001'),
            price:    Money::fromFloat(29.99),
            category: $this->category,
        );

        self::assertSame('WGT-PRO-001', $product->getSku());
        self::assertSame('Widget Pro', $product->getName());
        self::assertTrue($product->isActive());
        self::assertSame(2999, $product->getPrice()->getAmount());
        self::assertSame('USD', $product->getPrice()->getCurrency());
        self::assertNotEmpty($product->getUuid());
    }

    public function test_product_creation_records_domain_event(): void
    {
        $product = new Product(
            name:     'Widget Pro',
            sku:      new Sku('WGT-PRO-001'),
            price:    Money::fromFloat(29.99),
            category: $this->category,
        );

        $events = $product->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ProductCreatedEvent::class, $events[0]);
        self::assertSame('WGT-PRO-001', $events[0]->sku);
        self::assertSame('Widget Pro', $events[0]->name);
    }

    public function test_pull_domain_events_clears_the_list(): void
    {
        $product = new Product(
            name:     'Widget Pro',
            sku:      new Sku('WGT-PRO-001'),
            price:    Money::fromFloat(29.99),
            category: $this->category,
        );

        $product->pullDomainEvents();
        $events = $product->pullDomainEvents();

        self::assertEmpty($events);
    }

    public function test_product_update_records_domain_event(): void
    {
        $product = new Product(
            name:     'Old Name',
            sku:      new Sku('OLD-SKU-001'),
            price:    Money::fromFloat(10.00),
            category: $this->category,
        );

        $product->pullDomainEvents(); // clear creation event

        $newCategory = new Category('Office', 'Office supplies');
        $product->update('New Name', Money::fromFloat(15.00), $newCategory, 'New description');

        $events = $product->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ProductUpdatedEvent::class, $events[0]);
        self::assertSame('NEW NAME' === $product->getName(), false);
        self::assertSame('New Name', $product->getName());
        self::assertSame(1500, $product->getPrice()->getAmount());
    }

    public function test_deactivate_sets_product_inactive(): void
    {
        $product = new Product(
            name:     'Widget',
            sku:      new Sku('WGT-001'),
            price:    Money::fromFloat(5.00),
            category: $this->category,
        );

        self::assertTrue($product->isActive());

        $product->deactivate();

        self::assertFalse($product->isActive());
    }

    public function test_activate_restores_active_state(): void
    {
        $product = new Product(
            name:     'Widget',
            sku:      new Sku('WGT-001'),
            price:    Money::fromFloat(5.00),
            category: $this->category,
        );

        $product->deactivate();
        $product->activate();

        self::assertTrue($product->isActive());
    }
}
