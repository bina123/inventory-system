<?php

declare(strict_types=1);

namespace App\Module\Product\Application\Query;

use App\Module\Product\Domain\Product;

/** Read-model DTO returned by all product queries. */
final class ProductResponse implements \JsonSerializable
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $sku,
        public readonly ?string $description,
        public readonly float $price,
        public readonly string $currency,
        public readonly string $categoryUuid,
        public readonly string $categoryName,
        public readonly bool $isActive,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'uuid'          => $this->uuid,
            'name'          => $this->name,
            'sku'           => $this->sku,
            'description'   => $this->description,
            'price'         => $this->price,
            'currency'      => $this->currency,
            'category_uuid' => $this->categoryUuid,
            'category_name' => $this->categoryName,
            'is_active'     => $this->isActive,
            'created_at'    => $this->createdAt,
            'updated_at'    => $this->updatedAt,
        ];
    }

    public static function fromEntity(Product $product): self
    {
        return new self(
            uuid:         $product->getUuid(),
            name:         $product->getName(),
            sku:          $product->getSku(),
            description:  $product->getDescription(),
            price:        $product->getPrice()->toFloat(),
            currency:     $product->getPrice()->getCurrency(),
            categoryUuid: $product->getCategory()->getUuid(),
            categoryName: $product->getCategory()->getName(),
            isActive:     $product->isActive(),
            createdAt:    $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt:    $product->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
