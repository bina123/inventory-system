<?php

declare(strict_types=1);

namespace App\Module\Product\Application\Query;

use App\Module\Product\Domain\ProductRepositoryInterface;

final class ListProductsQueryHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @return array{data: list<ProductResponse>, meta: array{page: int, limit: int, total: int}}
     */
    public function handle(ListProductsQuery $query): array
    {
        $criteria = [];

        if ($query->categoryUuid !== null) {
            $criteria['categoryUuid'] = $query->categoryUuid;
        }

        if ($query->sku !== null) {
            $criteria['sku'] = $query->sku;
        }

        if ($query->isActive !== null) {
            $criteria['isActive'] = $query->isActive;
        }

        $products = $this->productRepository->findByCriteria($criteria, $query->page, $query->limit);
        $total    = $this->productRepository->countByCriteria($criteria);

        return [
            'data' => array_map(ProductResponse::fromEntity(...), $products),
            'meta' => [
                'page'  => $query->page,
                'limit' => $query->limit,
                'total' => $total,
            ],
        ];
    }
}
