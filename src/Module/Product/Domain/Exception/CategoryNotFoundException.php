<?php

declare(strict_types=1);

namespace App\Module\Product\Domain\Exception;

use App\Shared\Exception\ResourceNotFoundException;

final class CategoryNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $identifier)
    {
        parent::__construct('Category', $identifier);
    }
}
