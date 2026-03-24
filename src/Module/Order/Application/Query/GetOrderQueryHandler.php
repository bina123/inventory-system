<?php

declare(strict_types=1);

namespace App\Module\Order\Application\Query;

use App\Module\Order\Domain\Exception\OrderNotFoundException;
use App\Module\Order\Domain\OrderRepositoryInterface;

final class GetOrderQueryHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function handle(string $uuid): OrderResponse
    {
        $order = $this->orderRepository->findByUuid($uuid);

        if ($order === null) {
            throw new OrderNotFoundException($uuid);
        }

        return OrderResponse::fromEntity($order);
    }
}
