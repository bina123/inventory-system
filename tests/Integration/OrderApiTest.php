<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Module\Inventory\Domain\InventoryItem;
use App\Module\Product\Domain\Category;
use App\Module\Product\Domain\Product;
use App\Module\Product\Domain\ValueObject\Money;
use App\Module\Product\Domain\ValueObject\Sku;

final class OrderApiTest extends ApiTestCase
{
    private string $adminToken;
    private string $managerToken;
    private string $viewerToken;
    private string $productUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUser('admin@test.com', 'Password123!', 'ROLE_ADMIN');
        $this->createUser('manager@test.com', 'Password123!', 'ROLE_MANAGER');
        $this->createUser('viewer@test.com', 'Password123!', 'ROLE_VIEWER');

        $this->adminToken   = $this->getToken('admin@test.com', 'Password123!');
        $this->managerToken = $this->getToken('manager@test.com', 'Password123!');
        $this->viewerToken  = $this->getToken('viewer@test.com', 'Password123!');

        // Seed: category + product + inventory
        $category = new Category('Electronics');
        $this->em->persist($category);
        $this->em->flush();

        $product = new Product(
            name:     'Test Widget',
            sku:      new Sku('TEST-WGT-001'),
            price:    Money::fromFloat(29.99),
            category: $category,
        );
        $this->em->persist($product);
        $this->em->flush();

        $this->productUuid = $product->getUuid();

        $inventoryItem = new InventoryItem(
            productId:         $product->getId(),
            productUuid:       $product->getUuid(),
            productSku:        $product->getSku(),
            productName:       $product->getName(),
            initialQuantity:   100,
            lowStockThreshold: 10,
        );
        $this->em->persist($inventoryItem);
        $this->em->flush();
    }

    // -------------------------------------------------------------------------
    // Place order
    // -------------------------------------------------------------------------

    public function test_manager_can_place_order(): void
    {
        $this->jsonRequest('POST', '/api/v1/orders', [
            'customerEmail' => 'customer@example.com',
            'items'         => [
                ['productUuid' => $this->productUuid, 'quantity' => 3],
            ],
        ], $this->managerToken);

        $this->assertStatus(201);
        $data = $this->json()['data'];
        self::assertSame('confirmed', $data['status']);
        self::assertSame('customer@example.com', $data['customer_email']);
        self::assertCount(1, $data['items']);
        self::assertSame(3, $data['items'][0]['quantity']);
        self::assertEqualsWithDelta(89.97, $data['total_amount'], 0.01);
    }

    public function test_viewer_cannot_place_order(): void
    {
        $this->jsonRequest('POST', '/api/v1/orders', [
            'customerEmail' => 'customer@example.com',
            'items'         => [['productUuid' => $this->productUuid, 'quantity' => 1]],
        ], $this->viewerToken);

        $this->assertStatus(403);
    }

    public function test_place_order_with_missing_email_returns_422(): void
    {
        $this->jsonRequest('POST', '/api/v1/orders', [
            'customerEmail' => '',
            'items'         => [['productUuid' => $this->productUuid, 'quantity' => 1]],
        ], $this->managerToken);

        $this->assertStatus(422);
    }

    public function test_place_order_with_empty_items_returns_422(): void
    {
        $this->jsonRequest('POST', '/api/v1/orders', [
            'customerEmail' => 'customer@example.com',
            'items'         => [],
        ], $this->managerToken);

        $this->assertStatus(422);
    }

    public function test_place_order_insufficient_stock_returns_409(): void
    {
        $this->jsonRequest('POST', '/api/v1/orders', [
            'customerEmail' => 'customer@example.com',
            'items'         => [
                ['productUuid' => $this->productUuid, 'quantity' => 999],
            ],
        ], $this->managerToken);

        $this->assertStatus(409);
    }

    // -------------------------------------------------------------------------
    // Cancel order
    // -------------------------------------------------------------------------

    public function test_manager_can_cancel_order(): void
    {
        $this->jsonRequest('POST', '/api/v1/orders', [
            'customerEmail' => 'customer@example.com',
            'items'         => [['productUuid' => $this->productUuid, 'quantity' => 2]],
        ], $this->managerToken);
        $orderUuid = $this->json()['data']['uuid'];

        $this->jsonRequest('POST', "/api/v1/orders/{$orderUuid}/cancel", token: $this->managerToken);

        $this->assertStatus(200);
        self::assertSame('cancelled', $this->json()['data']['status']);
    }

    public function test_cancel_nonexistent_order_returns_404(): void
    {
        $this->jsonRequest('POST', '/api/v1/orders/00000000-0000-0000-0000-000000000000/cancel', token: $this->managerToken);

        $this->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Fulfil order
    // -------------------------------------------------------------------------

    public function test_admin_can_fulfil_order(): void
    {
        $this->jsonRequest('POST', '/api/v1/orders', [
            'customerEmail' => 'customer@example.com',
            'items'         => [['productUuid' => $this->productUuid, 'quantity' => 1]],
        ], $this->managerToken);
        $orderUuid = $this->json()['data']['uuid'];

        $this->jsonRequest('POST', "/api/v1/orders/{$orderUuid}/fulfil", token: $this->adminToken);

        $this->assertStatus(200);
        self::assertSame('fulfilled', $this->json()['data']['status']);
    }

    public function test_manager_cannot_fulfil_order(): void
    {
        $this->jsonRequest('POST', '/api/v1/orders', [
            'customerEmail' => 'customer@example.com',
            'items'         => [['productUuid' => $this->productUuid, 'quantity' => 1]],
        ], $this->managerToken);
        $orderUuid = $this->json()['data']['uuid'];

        $this->jsonRequest('POST', "/api/v1/orders/{$orderUuid}/fulfil", token: $this->managerToken);

        $this->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // List orders
    // -------------------------------------------------------------------------

    public function test_manager_can_list_orders(): void
    {
        $this->jsonRequest('GET', '/api/v1/orders', token: $this->managerToken);

        $this->assertStatus(200);
        self::assertArrayHasKey('data', $this->json());
        self::assertArrayHasKey('meta', $this->json());
    }

    public function test_viewer_cannot_list_orders(): void
    {
        $this->jsonRequest('GET', '/api/v1/orders', token: $this->viewerToken);

        $this->assertStatus(403);
    }
}
