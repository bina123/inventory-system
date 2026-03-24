<?php

declare(strict_types=1);

namespace App\Shared\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends ApiException
{
    /** @var array<string, string[]> */
    private readonly array $violations;

    public function __construct(ConstraintViolationListInterface $violationList)
    {
        $violations = [];

        foreach ($violationList as $violation) {
            $field = (string) $violation->getPropertyPath();
            $violations[$field][] = (string) $violation->getMessage();
        }

        $this->violations = $violations;

        parent::__construct('Validation failed.', Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @return array<string, string[]> */
    public function getContext(): array
    {
        return ['violations' => $this->violations];
    }
}
