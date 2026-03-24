<?php

declare(strict_types=1);

namespace App\Module\Order\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Order line item — owned entity of the Order aggregate.
 * Snapshot fields (productSku, productName, unitPriceAmount) capture
 * product data at the time the order was placed to ensure historical accuracy.
 */
#[ORM\Entity]
#[ORM\Table(name: 'order_items')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    /** Reference only — no cross-module entity join */
    #[ORM\Column(type: 'string', length: 100, name: 'product_uuid')]
    private string $productUuid;

    #[ORM\Column(type: 'string', length: 100, name: 'product_sku')]
    private string $productSku;

    #[ORM\Column(type: 'string', length: 200, name: 'product_name')]
    private string $productName;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    /** Unit price in cents at time of order */
    #[ORM\Column(type: 'integer', name: 'unit_price_amount')]
    private int $unitPriceAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'unit_price_currency')]
    private string $unitPriceCurrency;

    public function __construct(
        Order $order,
        string $productUuid,
        string $productSku,
        string $productName,
        int $quantity,
        int $unitPriceAmount,
        string $unitPriceCurrency,
    ) {
        $this->order             = $order;
        $this->productUuid       = $productUuid;
        $this->productSku        = $productSku;
        $this->productName       = $productName;
        $this->quantity          = $quantity;
        $this->unitPriceAmount   = $unitPriceAmount;
        $this->unitPriceCurrency = $unitPriceCurrency;
    }

    public function getLineTotalAmount(): int
    {
        return $this->unitPriceAmount * $this->quantity;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getProductUuid(): string
    {
        return $this->productUuid;
    }

    public function getProductSku(): string
    {
        return $this->productSku;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPriceAmount(): int
    {
        return $this->unitPriceAmount;
    }

    public function getUnitPriceCurrency(): string
    {
        return $this->unitPriceCurrency;
    }
}
