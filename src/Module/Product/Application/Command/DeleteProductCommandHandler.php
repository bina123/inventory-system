<?php

declare(strict_types=1);

namespace App\Module\Product\Application\Command;

use App\Module\Product\Domain\Exception\ProductNotFoundException;
use App\Module\Product\Domain\ProductRepositoryInterface;

/**
 * Soft-deletes (deactivates) a product.
 * Hard deletion is intentionally avoided to preserve order history integrity.
 */
final class DeleteProductCommandHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function handle(DeleteProductCommand $command): void
    {
        $product = $this->productRepository->findByUuid($command->productUuid);

        if ($product === null) {
            throw new ProductNotFoundException($command->productUuid);
        }

        $product->deactivate();
        $this->productRepository->save($product, flush: true);
    }
}
