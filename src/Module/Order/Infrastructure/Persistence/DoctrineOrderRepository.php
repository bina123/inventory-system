<?php

declare(strict_types=1);

namespace App\Module\Order\Infrastructure\Persistence;

use App\Module\Order\Domain\Order;
use App\Module\Order\Domain\OrderRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
final class DoctrineOrderRepository extends ServiceEntityRepository implements OrderRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByUuid(string $uuid): ?Order
    {
        return $this->createQueryBuilder('o')
            ->addSelect('i')
            ->leftJoin('o.items', 'i')
            ->where('o.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCriteria(array $criteria, int $page, int $limit): array
    {
        return $this->buildCriteriaQuery($criteria)
            ->addSelect('i')
            ->leftJoin('o.items', 'i')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('o.placedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByCriteria(array $criteria): int
    {
        return (int) $this->buildCriteriaQuery($criteria)
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Order $order, bool $flush = false): void
    {
        $this->getEntityManager()->persist($order);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    private function buildCriteriaQuery(array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o');

        if (isset($criteria['status'])) {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (isset($criteria['customerEmail'])) {
            $qb->andWhere('o.customerEmail = :customerEmail')
               ->setParameter('customerEmail', $criteria['customerEmail']);
        }

        return $qb;
    }
}
