<?php

declare(strict_types=1);

namespace App\Module\Order\Domain;

use App\Module\Order\Domain\ValueObject\OrderStatus;

interface OrderRepositoryInterface
{
    public function findByUuid(string $uuid): ?Order;

    /**
     * @param array<string, mixed> $criteria
     * @return list<Order>
     */
    public function findByCriteria(array $criteria, int $page, int $limit): array;

    public function countByCriteria(array $criteria): int;

    public function save(Order $order, bool $flush = false): void;
}
