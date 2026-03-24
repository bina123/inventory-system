<?php

declare(strict_types=1);

namespace App\Module\Product\Application\Query;

final class GetProductQuery
{
    public function __construct(
        public readonly string $uuid,
    ) {
    }
}
