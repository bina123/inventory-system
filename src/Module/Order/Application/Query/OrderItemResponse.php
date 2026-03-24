<?php

declare(strict_types=1);

namespace App\Module\Order\Application\Query;

final class OrderItemResponse implements \JsonSerializable
{
    public function __construct(
        public readonly string $productUuid,
        public readonly string $productSku,
        public readonly string $productName,
        public readonly int $quantity,
        public readonly float $unitPrice,
        public readonly string $unitPriceCurrency,
        public readonly float $lineTotal,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'product_uuid'        => $this->productUuid,
            'product_sku'         => $this->productSku,
            'product_name'        => $this->productName,
            'quantity'            => $this->quantity,
            'unit_price'          => $this->unitPrice,
            'unit_price_currency' => $this->unitPriceCurrency,
            'line_total'          => $this->lineTotal,
        ];
    }
}
