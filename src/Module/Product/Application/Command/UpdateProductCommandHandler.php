<?php

declare(strict_types=1);

namespace App\Module\Product\Application\Command;

use App\Module\Product\Application\Query\ProductResponse;
use App\Module\Product\Domain\CategoryRepositoryInterface;
use App\Module\Product\Domain\Exception\CategoryNotFoundException;
use App\Module\Product\Domain\Exception\ProductNotFoundException;
use App\Module\Product\Domain\ProductRepositoryInterface;
use App\Module\Product\Domain\ValueObject\Money;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class UpdateProductCommandHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(UpdateProductCommand $command): ProductResponse
    {
        $product = $this->productRepository->findByUuid($command->productUuid);

        if ($product === null) {
            throw new ProductNotFoundException($command->productUuid);
        }

        $category = $this->categoryRepository->findByUuid($command->categoryUuid);

        if ($category === null) {
            throw new CategoryNotFoundException($command->categoryUuid);
        }

        $product->update(
            name:        $command->name,
            price:       Money::fromFloat($command->price, $command->currency),
            category:    $category,
            description: $command->description,
        );

        $this->productRepository->save($product, flush: true);

        foreach ($product->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return ProductResponse::fromEntity($product);
    }
}
