<?php

declare(strict_types=1);

namespace App\Module\Product\Application\Query;

final class ListProductsQuery
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $limit = 25,
        public readonly ?string $categoryUuid = null,
        public readonly ?string $sku = null,
        public readonly ?bool $isActive = null,
    ) {
    }
}
