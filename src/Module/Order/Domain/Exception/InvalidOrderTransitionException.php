<?php

declare(strict_types=1);

namespace App\Module\Order\Domain\Exception;

use App\Module\Order\Domain\ValueObject\OrderStatus;
use App\Shared\Exception\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class InvalidOrderTransitionException extends ApiException
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct(
            sprintf(
                'Cannot transition order from "%s" to "%s".',
                $from->value,
                $to->value,
            ),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
