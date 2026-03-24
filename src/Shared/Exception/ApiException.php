<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * Base exception for all domain/application exceptions that should
 * be translated to a structured JSON API error response.
 */
abstract class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** Override in subclasses to add extra fields to the error response. */
    public function getContext(): array
    {
        return [];
    }
}
