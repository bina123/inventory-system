<?php

declare(strict_types=1);

namespace App\Module\Order\Application\Query;

use App\Module\Order\Domain\Order;
use App\Module\Order\Domain\OrderItem;

final class OrderResponse implements \JsonSerializable
{
    /** @param list<OrderItemResponse> $items */
    public function __construct(
        public readonly string $uuid,
        public readonly string $customerEmail,
        public readonly string $status,
        public readonly float $totalAmount,
        public readonly string $totalCurrency,
        public readonly ?string $notes,
        public readonly array $items,
        public readonly string $placedAt,
        public readonly string $updatedAt,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'uuid'           => $this->uuid,
            'customer_email' => $this->customerEmail,
            'status'         => $this->status,
            'total_amount'   => $this->totalAmount,
            'total_currency' => $this->totalCurrency,
            'notes'          => $this->notes,
            'items'          => $this->items,
            'placed_at'      => $this->placedAt,
            'updated_at'     => $this->updatedAt,
        ];
    }

    public static function fromEntity(Order $order): self
    {
        return new self(
            uuid:          $order->getUuid(),
            customerEmail: $order->getCustomerEmail(),
            status:        $order->getStatus()->value,
            totalAmount:   $order->getTotalAmount() / 100,
            totalCurrency: $order->getTotalCurrency(),
            notes:         $order->getNotes(),
            items:         array_values(array_map(
                static fn (OrderItem $item) => new OrderItemResponse(
                    productUuid:       $item->getProductUuid(),
                    productSku:        $item->getProductSku(),
                    productName:       $item->getProductName(),
                    quantity:          $item->getQuantity(),
                    unitPrice:         $item->getUnitPriceAmount() / 100,
                    unitPriceCurrency: $item->getUnitPriceCurrency(),
                    lineTotal:         $item->getLineTotalAmount() / 100,
                ),
                $order->getItems()->toArray(),
            )),
            placedAt:  $order->getPlacedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
