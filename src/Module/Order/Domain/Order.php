<?php

declare(strict_types=1);

namespace App\Module\Order\Domain;

use App\Module\Order\Domain\Event\OrderCancelledEvent;
use App\Module\Order\Domain\Event\OrderFulfilledEvent;
use App\Module\Order\Domain\Event\OrderPlacedEvent;
use App\Module\Order\Domain\Exception\InvalidOrderTransitionException;
use App\Module\Order\Domain\ValueObject\OrderStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Order Aggregate Root.
 *
 * Encapsulates the order lifecycle and enforces all state-machine transitions.
 * The Order does NOT directly reference Product or InventoryItem entities
 * to preserve module boundary isolation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'orders')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'guid', unique: true)]
    private string $uuid;

    #[ORM\Column(type: 'string', length: 180, name: 'customer_email')]
    private string $customerEmail;

    #[ORM\Column(type: 'string', length: 20, enumType: OrderStatus::class)]
    private OrderStatus $status;

    /** Total in cents */
    #[ORM\Column(type: 'integer', name: 'total_amount')]
    private int $totalAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'total_currency')]
    private string $totalCurrency;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(
        targetEntity: OrderItem::class,
        mappedBy: 'order',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $items;

    #[ORM\Column(type: 'datetime_immutable', name: 'placed_at')]
    private \DateTimeImmutable $placedAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    /** @var list<object> */
    private array $domainEvents = [];

    public function __construct(string $customerEmail, ?string $notes = null)
    {
        $this->uuid          = Uuid::v7()->toRfc4122();
        $this->customerEmail = $customerEmail;
        $this->status        = OrderStatus::PENDING;
        $this->totalAmount   = 0;
        $this->totalCurrency = 'USD';
        $this->notes         = $notes;
        $this->items         = new ArrayCollection();
        $this->placedAt      = new \DateTimeImmutable();
        $this->updatedAt     = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // -------------------------------------------------------------------------
    // Business operations
    // -------------------------------------------------------------------------

    public function addItem(
        string $productUuid,
        string $productSku,
        string $productName,
        int $quantity,
        int $unitPriceAmount,
        string $unitPriceCurrency,
    ): void {
        if ($this->status !== OrderStatus::PENDING) {
            throw new \LogicException('Items can only be added to a PENDING order.');
        }

        $item = new OrderItem(
            order:             $this,
            productUuid:       $productUuid,
            productSku:        $productSku,
            productName:       $productName,
            quantity:          $quantity,
            unitPriceAmount:   $unitPriceAmount,
            unitPriceCurrency: $unitPriceCurrency,
        );

        $this->items->add($item);
        $this->recalculateTotal();
    }

    /**
     * Finalise the order — transitions from PENDING to CONFIRMED.
     * Stock must be reserved before calling this.
     */
    public function confirm(): void
    {
        $this->transitionTo(OrderStatus::CONFIRMED);

        $this->recordEvent(new OrderPlacedEvent(
            orderUuid:     $this->uuid,
            customerEmail: $this->customerEmail,
            lineItems:     $this->buildLineItemPayload(),
        ));
    }

    public function startProcessing(): void
    {
        $this->transitionTo(OrderStatus::PROCESSING);
    }

    /**
     * Mark the order as fulfilled (shipped/delivered).
     * Inventory onHand should be decremented by the FulfilOrderCommandHandler.
     */
    public function fulfil(): void
    {
        $this->transitionTo(OrderStatus::FULFILLED);

        $this->recordEvent(new OrderFulfilledEvent(
            orderUuid:     $this->uuid,
            customerEmail: $this->customerEmail,
            lineItems:     $this->buildLineItemPayload(),
        ));
    }

    /**
     * Cancel the order. Reserved stock must be released by the CancelOrderCommandHandler.
     */
    public function cancel(): void
    {
        $this->transitionTo(OrderStatus::CANCELLED);

        $this->recordEvent(new OrderCancelledEvent(
            orderUuid:     $this->uuid,
            customerEmail: $this->customerEmail,
            lineItems:     $this->buildLineItemPayload(),
        ));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function transitionTo(OrderStatus $next): void
    {
        if (!$this->status->canTransitionTo($next)) {
            throw new InvalidOrderTransitionException($this->status, $next);
        }

        $this->status = $next;
    }

    private function recalculateTotal(): void
    {
        $this->totalAmount = array_reduce(
            $this->items->toArray(),
            static fn (int $carry, OrderItem $item) => $carry + $item->getLineTotalAmount(),
            0,
        );
    }

    /** @return list<array{productUuid: string, productSku: string, quantity: int}> */
    private function buildLineItemPayload(): array
    {
        return array_values(array_map(
            static fn (OrderItem $item) => [
                'productUuid' => $item->getProductUuid(),
                'productSku'  => $item->getProductSku(),
                'quantity'    => $item->getQuantity(),
            ],
            $this->items->toArray(),
        ));
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

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getTotalAmount(): int
    {
        return $this->totalAmount;
    }

    public function getTotalCurrency(): string
    {
        return $this->totalCurrency;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getPlacedAt(): \DateTimeImmutable
    {
        return $this->placedAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
