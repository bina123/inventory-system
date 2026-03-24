<?php

declare(strict_types=1);

namespace App\Module\Product\Domain;

interface CategoryRepositoryInterface
{
    public function findById(int $id): ?Category;

    public function findByUuid(string $uuid): ?Category;

    public function findByName(string $name): ?Category;

    /** @return list<Category> */
    public function findAll(): array;

    public function save(Category $category, bool $flush = false): void;

    public function remove(Category $category, bool $flush = false): void;
}
