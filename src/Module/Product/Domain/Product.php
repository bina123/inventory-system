<?php

declare(strict_types=1);

namespace App\Module\Product\Domain;

use App\Module\Product\Domain\Event\ProductCreatedEvent;
use App\Module\Product\Domain\Event\ProductUpdatedEvent;
use App\Module\Product\Domain\ValueObject\Money;
use App\Module\Product\Domain\ValueObject\Sku;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Product Aggregate Root.
 *
 * Business rules enforced here:
 * - SKU is immutable after creation (use a new product for a new SKU)
 * - Price cannot be negative (enforced by Money VO)
 * - Deactivated products cannot be purchased (enforced by OrderService)
 */
#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'guid', unique: true)]
    private string $uuid;

    #[ORM\Column(type: 'string', length: 200)]
    private string $name;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $sku;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Embedded(class: Money::class, columnPrefix: false)]
    private Money $price;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Category $category;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var list<object> Collected domain events, dispatched by the handler after flush. */
    private array $domainEvents = [];

    public function __construct(
        string $name,
        Sku $sku,
        Money $price,
        Category $category,
        ?string $description = null,
    ) {
        $this->uuid        = Uuid::v7()->toRfc4122();
        $this->name        = $name;
        $this->sku         = $sku->getValue();
        $this->price       = $price;
        $this->category    = $category;
        $this->description = $description;
        $this->isActive    = true;
        $this->createdAt   = new \DateTimeImmutable();
        $this->updatedAt   = new \DateTimeImmutable();

        $this->recordEvent(new ProductCreatedEvent($this->uuid, $this->sku, $this->name));
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(string $name, Money $price, Category $category, ?string $description): void
    {
        $this->name        = $name;
        $this->price       = $price;
        $this->category    = $category;
        $this->description = $description;

        $this->recordEvent(new ProductUpdatedEvent($this->uuid, $this->sku));
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    // -------------------------------------------------------------------------
    // Domain event collection (pull-based pattern)
    // -------------------------------------------------------------------------

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

    public function getName(): string
    {
        return $this->name;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
