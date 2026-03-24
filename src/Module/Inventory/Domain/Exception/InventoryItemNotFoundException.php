<?php

declare(strict_types=1);

namespace App\Module\Inventory\Domain\Exception;

use App\Shared\Exception\ResourceNotFoundException;

final class InventoryItemNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $identifier)
    {
        parent::__construct('InventoryItem', $identifier);
    }
}
