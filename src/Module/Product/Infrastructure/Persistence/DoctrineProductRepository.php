<?php

declare(strict_types=1);

namespace App\Module\Product\Infrastructure\Persistence;

use App\Module\Product\Domain\Product;
use App\Module\Product\Domain\ProductRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
final class DoctrineProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findById(int $id): ?Product
    {
        return $this->find($id);
    }

    public function findByUuid(string $uuid): ?Product
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->findOneBy(['sku' => $sku]);
    }

    public function findByCriteria(array $criteria, int $page, int $limit): array
    {
        $qb = $this->buildCriteriaQuery($criteria);

        return $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByCriteria(array $criteria): int
    {
        $qb = $this->buildCriteriaQuery($criteria);

        return (int) $qb
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Product $product, bool $flush = false): void
    {
        $this->getEntityManager()->persist($product);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $product, bool $flush = false): void
    {
        $this->getEntityManager()->remove($product);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    private function buildCriteriaQuery(array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.category', 'c');

        if (isset($criteria['categoryUuid'])) {
            $qb->andWhere('c.uuid = :categoryUuid')
               ->setParameter('categoryUuid', $criteria['categoryUuid']);
        }

        if (isset($criteria['sku'])) {
            $qb->andWhere('p.sku = :sku')
               ->setParameter('sku', strtoupper($criteria['sku']));
        }

        if (isset($criteria['isActive'])) {
            $qb->andWhere('p.isActive = :isActive')
               ->setParameter('isActive', $criteria['isActive']);
        }

        return $qb;
    }
}
