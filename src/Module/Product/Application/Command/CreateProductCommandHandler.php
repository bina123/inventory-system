<?php

declare(strict_types=1);

namespace App\Module\Product\Application\Command;

use App\Module\Product\Application\Query\ProductResponse;
use App\Module\Product\Domain\CategoryRepositoryInterface;
use App\Module\Product\Domain\Exception\CategoryNotFoundException;
use App\Module\Product\Domain\Exception\DuplicateSkuException;
use App\Module\Product\Domain\Product;
use App\Module\Product\Domain\ProductRepositoryInterface;
use App\Module\Product\Domain\ValueObject\Money;
use App\Module\Product\Domain\ValueObject\Sku;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class CreateProductCommandHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(CreateProductCommand $command): ProductResponse
    {
        $sku = new Sku($command->sku);

        if ($this->productRepository->findBySku($sku->getValue()) !== null) {
            throw new DuplicateSkuException($sku->getValue());
        }

        $category = $this->categoryRepository->findByUuid($command->categoryUuid);

        if ($category === null) {
            throw new CategoryNotFoundException($command->categoryUuid);
        }

        $product = new Product(
            name:        $command->name,
            sku:         $sku,
            price:       Money::fromFloat($command->price, $command->currency),
            category:    $category,
            description: $command->description,
        );

        $this->productRepository->save($product, flush: true);

        // Dispatch collected domain events after the transaction commits
        foreach ($product->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return ProductResponse::fromEntity($product);
    }
}
