<?php

declare(strict_types=1);

namespace App\Module\Order\Domain\Exception;

use App\Shared\Exception\ResourceNotFoundException;

final class OrderNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $identifier)
    {
        parent::__construct('Order', $identifier);
    }
}
