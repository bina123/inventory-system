<?php

declare(strict_types=1);

namespace App\Module\Product\Application\Query;

use App\Module\Product\Domain\Exception\ProductNotFoundException;
use App\Module\Product\Domain\ProductRepositoryInterface;

final class GetProductQueryHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function handle(GetProductQuery $query): ProductResponse
    {
        $product = $this->productRepository->findByUuid($query->uuid);

        if ($product === null) {
            throw new ProductNotFoundException($query->uuid);
        }

        return ProductResponse::fromEntity($product);
    }
}
