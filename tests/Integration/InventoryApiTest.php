<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Module\Inventory\Domain\InventoryItem;
use App\Module\Product\Domain\Category;
use App\Module\Product\Domain\Product;
use App\Module\Product\Domain\ValueObject\Money;
use App\Module\Product\Domain\ValueObject\Sku;

final class InventoryApiTest extends ApiTestCase
{
    private string $managerToken;
    private string $viewerToken;
    private string $productUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUser('manager@test.com', 'Password123!', 'ROLE_MANAGER');
        $this->createUser('viewer@test.com', 'Password123!', 'ROLE_VIEWER');

        $this->managerToken = $this->getToken('manager@test.com', 'Password123!');
        $this->viewerToken  = $this->getToken('viewer@test.com', 'Password123!');

        $category = new Category('Test Category');
        $this->em->persist($category);
        $this->em->flush();

        $product = new Product(
            name:     'Inventory Widget',
            sku:      new Sku('INV-WGT-001'),
            price:    Money::fromFloat(15.00),
            category: $category,
        );
        $this->em->persist($product);
        $this->em->flush();

        $this->productUuid = $product->getUuid();

        $item = new InventoryItem(
            productId:         $product->getId(),
            productUuid:       $product->getUuid(),
            productSku:        $product->getSku(),
            productName:       $product->getName(),
            initialQuantity:   50,
            lowStockThreshold: 10,
        );
        $this->em->persist($item);
        $this->em->flush();
    }

    public function test_viewer_can_list_inventory(): void
    {
        $this->jsonRequest('GET', '/api/v1/inventory', token: $this->viewerToken);

        $this->assertStatus(200);
        self::assertArrayHasKey('data', $this->json());
    }

    public function test_viewer_can_get_inventory_for_product(): void
    {
        $this->jsonRequest('GET', "/api/v1/inventory/{$this->productUuid}", token: $this->viewerToken);

        $this->assertStatus(200);
        $data = $this->json()['data'];
        self::assertSame(50, $data['quantity_on_hand']);
        self::assertSame(0, $data['quantity_reserved']);
        self::assertSame(50, $data['quantity_available']);
    }

    public function test_manager_can_increase_stock(): void
    {
        $this->jsonRequest('POST', "/api/v1/inventory/{$this->productUuid}/adjust", [
            'quantity' => 25,
            'reason'   => 'Received from supplier',
        ], $this->managerToken);

        $this->assertStatus(200);
        self::assertSame(75, $this->json()['data']['quantity_on_hand']);
    }

    public function test_manager_can_decrease_stock(): void
    {
        $this->jsonRequest('POST', "/api/v1/inventory/{$this->productUuid}/adjust", [
            'quantity' => -10,
            'reason'   => 'Damaged goods write-off',
        ], $this->managerToken);

        $this->assertStatus(200);
        self::assertSame(40, $this->json()['data']['quantity_on_hand']);
    }

    public function test_viewer_cannot_adjust_stock(): void
    {
        $this->jsonRequest('POST', "/api/v1/inventory/{$this->productUuid}/adjust", [
            'quantity' => 10,
            'reason'   => 'Test',
        ], $this->viewerToken);

        $this->assertStatus(403);
    }

    public function test_adjust_zero_quantity_returns_422(): void
    {
        $this->jsonRequest('POST', "/api/v1/inventory/{$this->productUuid}/adjust", [
            'quantity' => 0,
            'reason'   => 'Zero is invalid',
        ], $this->managerToken);

        $this->assertStatus(422);
    }

    public function test_decrease_beyond_available_returns_409(): void
    {
        $this->jsonRequest('POST', "/api/v1/inventory/{$this->productUuid}/adjust", [
            'quantity' => -999,
            'reason'   => 'Too much decrease',
        ], $this->managerToken);

        $this->assertStatus(409);
    }

    public function test_manager_can_view_low_stock_items(): void
    {
        $this->jsonRequest('GET', '/api/v1/inventory/low-stock', token: $this->managerToken);

        $this->assertStatus(200);
        self::assertArrayHasKey('data', $this->json());
    }

    public function test_viewer_cannot_view_low_stock(): void
    {
        $this->jsonRequest('GET', '/api/v1/inventory/low-stock', token: $this->viewerToken);

        $this->assertStatus(403);
    }
}
