<?php

declare(strict_types=1);

namespace App\Module\Product\Domain;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function findByUuid(string $uuid): ?Product;

    public function findBySku(string $sku): ?Product;

    /**
     * @param array<string, mixed> $criteria
     * @return list<Product>
     */
    public function findByCriteria(array $criteria, int $page, int $limit): array;

    public function countByCriteria(array $criteria): int;

    public function save(Product $product, bool $flush = false): void;

    public function remove(Product $product, bool $flush = false): void;
}
