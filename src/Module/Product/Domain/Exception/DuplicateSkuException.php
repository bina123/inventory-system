<?php

declare(strict_types=1);

namespace App\Module\Product\Domain\Exception;

use App\Shared\Exception\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class DuplicateSkuException extends ApiException
{
    public function __construct(string $sku)
    {
        parent::__construct(
            sprintf('A product with SKU "%s" already exists.', $sku),
            Response::HTTP_CONFLICT,
        );
    }
}
