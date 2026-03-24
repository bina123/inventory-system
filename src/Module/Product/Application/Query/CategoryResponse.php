<?php

declare(strict_types=1);

namespace App\Module\Product\Application\Query;

use App\Module\Product\Domain\Category;

final class CategoryResponse
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    public static function fromEntity(Category $category): self
    {
        return new self(
            uuid:        $category->getUuid(),
            name:        $category->getName(),
            description: $category->getDescription(),
            createdAt:   $category->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt:   $category->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
