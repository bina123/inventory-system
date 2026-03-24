<?php

declare(strict_types=1);

namespace App\Module\Inventory\Infrastructure\Persistence;

use App\Module\Inventory\Domain\InventoryItem;
use App\Module\Inventory\Domain\InventoryRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryItem>
 */
final class DoctrineInventoryRepository extends ServiceEntityRepository implements InventoryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryItem::class);
    }

    public function findByProductId(int $productId): ?InventoryItem
    {
        return $this->findOneBy(['productId' => $productId]);
    }

    public function findByProductUuid(string $productUuid): ?InventoryItem
    {
        return $this->findOneBy(['productUuid' => $productUuid]);
    }

    public function findByUuid(string $uuid): ?InventoryItem
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findAll(int $page = 1, int $limit = 25): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.productSku', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLowStockItems(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.quantityOnHand <= i.lowStockThreshold')
            ->orderBy('i.quantityOnHand', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(InventoryItem $item, bool $flush = false): void
    {
        $this->getEntityManager()->persist($item);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
