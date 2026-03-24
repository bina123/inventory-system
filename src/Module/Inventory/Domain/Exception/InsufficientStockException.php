<?php

declare(strict_types=1);

namespace App\Module\Inventory\Domain\Exception;

use App\Shared\Exception\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class InsufficientStockException extends ApiException
{
    public function __construct(
        private readonly string $productSku,
        private readonly int $requested,
        private readonly int $available,
    ) {
        parent::__construct(
            sprintf(
                'Insufficient stock for product "%s": requested %d, available %d.',
                $productSku,
                $requested,
                $available,
            ),
            Response::HTTP_CONFLICT,
        );
    }

    public function getContext(): array
    {
        return [
            'product_sku' => $this->productSku,
            'requested'   => $this->requested,
            'available'   => $this->available,
        ];
    }
}
