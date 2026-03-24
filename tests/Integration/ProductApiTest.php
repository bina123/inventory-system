<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Module\Product\Domain\Category;

final class ProductApiTest extends ApiTestCase
{
    private string $adminToken;
    private string $managerToken;
    private string $viewerToken;
    private string $categoryUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUser('admin@test.com', 'Password123!', 'ROLE_ADMIN');
        $this->createUser('manager@test.com', 'Password123!', 'ROLE_MANAGER');
        $this->createUser('viewer@test.com', 'Password123!', 'ROLE_VIEWER');

        $this->adminToken   = $this->getToken('admin@test.com', 'Password123!');
        $this->managerToken = $this->getToken('manager@test.com', 'Password123!');
        $this->viewerToken  = $this->getToken('viewer@test.com', 'Password123!');

        // Seed a category directly via Doctrine
        $category = new Category('Electronics', 'Consumer electronics');
        $this->em->persist($category);
        $this->em->flush();
        $this->categoryUuid = $category->getUuid();
    }

    // -------------------------------------------------------------------------
    // List products
    // -------------------------------------------------------------------------

    public function test_list_products_accessible_to_viewer(): void
    {
        $this->jsonRequest('GET', '/api/v1/products', token: $this->viewerToken);

        $this->assertStatus(200);
        self::assertArrayHasKey('data', $this->json());
        self::assertArrayHasKey('meta', $this->json());
    }

    public function test_list_products_without_token_returns_401(): void
    {
        $this->jsonRequest('GET', '/api/v1/products');

        $this->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Create product
    // -------------------------------------------------------------------------

    public function test_manager_can_create_product(): void
    {
        $this->jsonRequest('POST', '/api/v1/products', [
            'name'         => 'Widget Pro',
            'sku'          => 'WGT-PRO-001',
            'price'        => 29.99,
            'currency'     => 'USD',
            'categoryUuid' => $this->categoryUuid,
            'description'  => 'A great widget',
        ], $this->managerToken);

        $this->assertStatus(201);
        $data = $this->json()['data'];
        self::assertSame('WGT-PRO-001', $data['sku']);
        self::assertSame('Widget Pro', $data['name']);
        self::assertEqualsWithDelta(29.99, $data['price'], 0.01);
        self::assertTrue($data['is_active']);
    }

    public function test_viewer_cannot_create_product(): void
    {
        $this->jsonRequest('POST', '/api/v1/products', [
            'name'         => 'Widget',
            'sku'          => 'WGT-001',
            'price'        => 10.00,
            'currency'     => 'USD',
            'categoryUuid' => $this->categoryUuid,
        ], $this->viewerToken);

        $this->assertStatus(403);
    }

    public function test_create_product_with_duplicate_sku_returns_409(): void
    {
        $payload = [
            'name'         => 'Widget',
            'sku'          => 'WGT-DUPE-001',
            'price'        => 10.00,
            'currency'     => 'USD',
            'categoryUuid' => $this->categoryUuid,
        ];

        $this->jsonRequest('POST', '/api/v1/products', $payload, $this->managerToken);
        $this->assertStatus(201);

        // Second request with same SKU
        $this->jsonRequest('POST', '/api/v1/products', $payload, $this->managerToken);
        $this->assertStatus(409);
    }

    public function test_create_product_with_missing_fields_returns_422(): void
    {
        $this->jsonRequest('POST', '/api/v1/products', [
            'name' => '',   // blank
            'sku'  => '',   // blank
        ], $this->managerToken);

        $this->assertStatus(422);
        self::assertArrayHasKey('violations', $this->json()['error']);
    }

    // -------------------------------------------------------------------------
    // Get single product
    // -------------------------------------------------------------------------

    public function test_get_product_returns_correct_data(): void
    {
        // Create product first
        $this->jsonRequest('POST', '/api/v1/products', [
            'name'         => 'Gadget X',
            'sku'          => 'GDG-X-001',
            'price'        => 49.99,
            'currency'     => 'USD',
            'categoryUuid' => $this->categoryUuid,
        ], $this->managerToken);
        $uuid = $this->json()['data']['uuid'];

        $this->jsonRequest('GET', "/api/v1/products/{$uuid}", token: $this->viewerToken);

        $this->assertStatus(200);
        self::assertSame('GDG-X-001', $this->json()['data']['sku']);
    }

    public function test_get_nonexistent_product_returns_404(): void
    {
        $this->jsonRequest('GET', '/api/v1/products/00000000-0000-0000-0000-000000000000', token: $this->viewerToken);

        $this->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update product
    // -------------------------------------------------------------------------

    public function test_manager_can_update_product(): void
    {
        $this->jsonRequest('POST', '/api/v1/products', [
            'name'         => 'Old Name',
            'sku'          => 'UPD-001',
            'price'        => 10.00,
            'currency'     => 'USD',
            'categoryUuid' => $this->categoryUuid,
        ], $this->managerToken);
        $uuid = $this->json()['data']['uuid'];

        $this->jsonRequest('PUT', "/api/v1/products/{$uuid}", [
            'name'         => 'New Name',
            'price'        => 19.99,
            'currency'     => 'USD',
            'categoryUuid' => $this->categoryUuid,
        ], $this->managerToken);

        $this->assertStatus(200);
        self::assertSame('New Name', $this->json()['data']['name']);
        self::assertEqualsWithDelta(19.99, $this->json()['data']['price'], 0.01);
    }

    // -------------------------------------------------------------------------
    // Soft-delete product
    // -------------------------------------------------------------------------

    public function test_admin_can_delete_product(): void
    {
        $this->jsonRequest('POST', '/api/v1/products', [
            'name'         => 'To Delete',
            'sku'          => 'DEL-001',
            'price'        => 5.00,
            'currency'     => 'USD',
            'categoryUuid' => $this->categoryUuid,
        ], $this->managerToken);
        $uuid = $this->json()['data']['uuid'];

        $this->jsonRequest('DELETE', "/api/v1/products/{$uuid}", token: $this->adminToken);

        $this->assertStatus(204);
    }

    public function test_manager_cannot_delete_product(): void
    {
        $this->jsonRequest('POST', '/api/v1/products', [
            'name'         => 'Cannot Delete',
            'sku'          => 'NODEL-001',
            'price'        => 5.00,
            'currency'     => 'USD',
            'categoryUuid' => $this->categoryUuid,
        ], $this->managerToken);
        $uuid = $this->json()['data']['uuid'];

        $this->jsonRequest('DELETE', "/api/v1/products/{$uuid}", token: $this->managerToken);

        $this->assertStatus(403);
    }
}
