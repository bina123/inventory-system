<?php

declare(strict_types=1);

namespace App\Shared\Exception;

use Symfony\Component\HttpFoundation\Response;

class ResourceNotFoundException extends ApiException
{
    public function __construct(string $resourceType, string $identifier, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('%s with identifier "%s" was not found.', $resourceType, $identifier),
            Response::HTTP_NOT_FOUND,
            $previous,
        );
    }
}
