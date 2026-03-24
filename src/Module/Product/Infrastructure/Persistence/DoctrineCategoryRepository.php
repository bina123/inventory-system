<?php

declare(strict_types=1);

namespace App\Module\Product\Infrastructure\Persistence;

use App\Module\Product\Domain\Category;
use App\Module\Product\Domain\CategoryRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
final class DoctrineCategoryRepository extends ServiceEntityRepository implements CategoryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findById(int $id): ?Category
    {
        return $this->find($id);
    }

    public function findByUuid(string $uuid): ?Category
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findByName(string $name): ?Category
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function findAll(): array
    {
        return parent::findAll();
    }

    public function save(Category $category, bool $flush = false): void
    {
        $this->getEntityManager()->persist($category);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Category $category, bool $flush = false): void
    {
        $this->getEntityManager()->remove($category);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
