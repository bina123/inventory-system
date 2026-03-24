<?php

declare(strict_types=1);

namespace App\Module\Inventory\Domain;

use App\Module\Inventory\Domain\Event\LowStockAlertEvent;
use App\Module\Inventory\Domain\Event\StockUpdatedEvent;
use App\Module\Inventory\Domain\Exception\InsufficientStockException;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Inventory Item Aggregate Root.
 *
 * Tracks stock for a single product using two quantities:
 *   - quantityOnHand: physical units available in the warehouse
 *   - quantityReserved: units committed to pending/confirmed orders (subset of onHand)
 *
 * Available quantity = quantityOnHand - quantityReserved
 *
 * Business rules enforced:
 *   - Cannot reserve more than available quantity
 *   - Cannot release more than currently reserved
 *   - Stock adjustments can only go non-negative
 *   - LowStockAlertEvent is raised when onHand drops below the configured threshold
 */
#[ORM\Entity]
#[ORM\Table(name: 'inventory_items')]
#[ORM\HasLifecycleCallbacks]
class InventoryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'guid', unique: true)]
    private string $uuid;

    /** Foreign key reference only — no cross-module entity join. */
    #[ORM\Column(type: 'integer', unique: true, name: 'product_id')]
    private int $productId;

    #[ORM\Column(type: 'string', length: 100, name: 'product_uuid')]
    private string $productUuid;

    #[ORM\Column(type: 'string', length: 100, name: 'product_sku')]
    private string $productSku;

    #[ORM\Column(type: 'string', length: 200, name: 'product_name')]
    private string $productName;

    #[ORM\Column(type: 'integer')]
    private int $quantityOnHand;

    #[ORM\Column(type: 'integer')]
    private int $quantityReserved;

    #[ORM\Column(type: 'integer')]
    private int $lowStockThreshold;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var list<object> */
    private array $domainEvents = [];

    public function __construct(
        int $productId,
        string $productUuid,
        string $productSku,
        string $productName,
        int $initialQuantity = 0,
        int $lowStockThreshold = 10,
    ) {
        $this->uuid              = Uuid::v7()->toRfc4122();
        $this->productId         = $productId;
        $this->productUuid       = $productUuid;
        $this->productSku        = $productSku;
        $this->productName       = $productName;
        $this->quantityOnHand    = $initialQuantity;
        $this->quantityReserved  = 0;
        $this->lowStockThreshold = $lowStockThreshold;
        $this->version           = 1;
        $this->updatedAt         = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // -------------------------------------------------------------------------
    // Business operations
    // -------------------------------------------------------------------------

    /**
     * Increase stock (e.g., goods received from supplier).
     */
    public function increaseStock(int $quantity, string $reason = 'Stock received'): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity to increase must be positive.');
        }

        $previous                = $this->quantityOnHand;
        $this->quantityOnHand   += $quantity;

        $this->recordEvent(new StockUpdatedEvent(
            productUuid:      $this->productUuid,
            productSku:       $this->productSku,
            previousQuantity: $previous,
            newQuantity:      $this->quantityOnHand,
            reason:           $reason,
        ));
    }

    /**
     * Decrease physical stock (e.g., manual write-off, damage).
     */
    public function decreaseStock(int $quantity, string $reason = 'Stock adjustment'): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity to decrease must be positive.');
        }

        if ($this->getAvailableQuantity() < $quantity) {
            throw new InsufficientStockException($this->productSku, $quantity, $this->getAvailableQuantity());
        }

        $previous              = $this->quantityOnHand;
        $this->quantityOnHand -= $quantity;

        $this->recordEvent(new StockUpdatedEvent(
            productUuid:      $this->productUuid,
            productSku:       $this->productSku,
            previousQuantity: $previous,
            newQuantity:      $this->quantityOnHand,
            reason:           $reason,
        ));

        $this->checkLowStockThreshold();
    }

    /**
     * Reserve stock for a pending/confirmed order.
     * Reduces available quantity but does not reduce onHand.
     */
    public function reserve(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Reservation quantity must be positive.');
        }

        if ($this->getAvailableQuantity() < $quantity) {
            throw new InsufficientStockException($this->productSku, $quantity, $this->getAvailableQuantity());
        }

        $this->quantityReserved += $quantity;

        $this->checkLowStockThreshold();
    }

    /**
     * Release previously reserved stock (e.g., order cancelled).
     */
    public function release(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Release quantity must be positive.');
        }

        if ($this->quantityReserved < $quantity) {
            throw new \LogicException(
                sprintf(
                    'Cannot release %d units: only %d are reserved for product "%s".',
                    $quantity,
                    $this->quantityReserved,
                    $this->productSku,
                ),
            );
        }

        $this->quantityReserved -= $quantity;
    }

    /**
     * Commit reserved stock to fulfilled (reduces onHand).
     * Called when an order is shipped/fulfilled.
     */
    public function commitReserved(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Commit quantity must be positive.');
        }

        if ($this->quantityReserved < $quantity) {
            throw new \LogicException(
                sprintf(
                    'Cannot commit %d units: only %d are reserved for product "%s".',
                    $quantity,
                    $this->quantityReserved,
                    $this->productSku,
                ),
            );
        }

        $previous                = $this->quantityOnHand;
        $this->quantityReserved -= $quantity;
        $this->quantityOnHand   -= $quantity;

        $this->recordEvent(new StockUpdatedEvent(
            productUuid:      $this->productUuid,
            productSku:       $this->productSku,
            previousQuantity: $previous,
            newQuantity:      $this->quantityOnHand,
            reason:           'Order fulfilled',
        ));
    }

    public function updateThreshold(int $newThreshold): void
    {
        $this->lowStockThreshold = max(0, $newThreshold);
    }

    // -------------------------------------------------------------------------
    // Domain event handling
    // -------------------------------------------------------------------------

    private function checkLowStockThreshold(): void
    {
        if ($this->quantityOnHand <= $this->lowStockThreshold) {
            $this->recordEvent(new LowStockAlertEvent(
                productUuid:     $this->productUuid,
                productSku:      $this->productSku,
                productName:     $this->productName,
                currentQuantity: $this->quantityOnHand,
                threshold:       $this->lowStockThreshold,
            ));
        }
    }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return list<object> */
    public function pullDomainEvents(): array
    {
        $events             = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getProductId(): int
    {
        return $this->productId;
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

    public function getQuantityOnHand(): int
    {
        return $this->quantityOnHand;
    }

    public function getQuantityReserved(): int
    {
        return $this->quantityReserved;
    }

    public function getAvailableQuantity(): int
    {
        return $this->quantityOnHand - $this->quantityReserved;
    }

    public function getLowStockThreshold(): int
    {
        return $this->lowStockThreshold;
    }

    public function isLowStock(): bool
    {
        return $this->quantityOnHand <= $this->lowStockThreshold;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
