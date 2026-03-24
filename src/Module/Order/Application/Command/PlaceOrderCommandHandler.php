<?php

declare(strict_types=1);

namespace App\Module\Order\Application\Command;

use App\Module\Inventory\Domain\Exception\InventoryItemNotFoundException;
use App\Module\Inventory\Domain\InventoryRepositoryInterface;
use App\Module\Order\Application\Query\OrderResponse;
use App\Module\Order\Domain\Order;
use App\Module\Order\Domain\OrderRepositoryInterface;
use App\Module\Product\Domain\Exception\ProductNotFoundException;
use App\Module\Product\Domain\ProductRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Orchestrates the order placement workflow:
 *
 * 1. Validate all products exist and are active
 * 2. Snapshot price/name from Product at time of order
 * 3. Reserve stock in Inventory for each line item
 * 4. Create Order aggregate + add line items
 * 5. Confirm the order (transitions PENDING → CONFIRMED)
 * 6. Persist & flush all changes
 * 7. Dispatch collected domain events
 *
 * If any reservation fails (InsufficientStockException), the entire
 * operation is rolled back and the exception propagates to the controller.
 */
final class PlaceOrderCommandHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly InventoryRepositoryInterface $inventoryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(PlaceOrderCommand $command): OrderResponse
    {
        $order = new Order($command->customerEmail, $command->notes);

        foreach ($command->items as $lineItem) {
            $productUuid = $lineItem['productUuid'];
            $quantity    = (int) $lineItem['quantity'];

            // --- Product lookup & validation ---
            $product = $this->productRepository->findByUuid($productUuid);

            if ($product === null) {
                throw new ProductNotFoundException($productUuid);
            }

            if (!$product->isActive()) {
                throw new \DomainException(
                    sprintf('Product "%s" is not available for ordering.', $product->getSku()),
                );
            }

            // --- Inventory reservation ---
            $inventoryItem = $this->inventoryRepository->findByProductUuid($productUuid);

            if ($inventoryItem === null) {
                throw new InventoryItemNotFoundException($productUuid);
            }

            // reserve() throws InsufficientStockException if stock unavailable
            $inventoryItem->reserve($quantity);
            $this->inventoryRepository->save($inventoryItem);

            // Dispatch inventory domain events (e.g., LowStockAlertEvent)
            foreach ($inventoryItem->pullDomainEvents() as $event) {
                $this->eventDispatcher->dispatch($event);
            }

            // --- Add line item with price snapshot ---
            $order->addItem(
                productUuid:       $product->getUuid(),
                productSku:        $product->getSku(),
                productName:       $product->getName(),
                quantity:          $quantity,
                unitPriceAmount:   $product->getPrice()->getAmount(),
                unitPriceCurrency: $product->getPrice()->getCurrency(),
            );
        }

        // Transition PENDING → CONFIRMED; records OrderPlacedEvent
        $order->confirm();

        $this->orderRepository->save($order, flush: true);

        // Dispatch order domain events after flush
        foreach ($order->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return OrderResponse::fromEntity($order);
    }
}
