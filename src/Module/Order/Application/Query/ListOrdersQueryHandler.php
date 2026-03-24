<?php

declare(strict_types=1);

namespace App\Module\Order\Application\Query;

use App\Module\Order\Domain\OrderRepositoryInterface;

final class ListOrdersQueryHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<OrderResponse>, meta: array{page: int, limit: int, total: int}}
     */
    public function handle(array $filters = [], int $page = 1, int $limit = 25): array
    {
        $orders = $this->orderRepository->findByCriteria($filters, $page, $limit);
        $total  = $this->orderRepository->countByCriteria($filters);

        return [
            'data' => array_map(OrderResponse::fromEntity(...), $orders),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total],
        ];
    }
}
