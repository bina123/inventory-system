<?php

declare(strict_types=1);

namespace App\Shared\EventListener;

use App\Shared\Exception\ApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Converts all uncaught exceptions into a consistent JSON API error structure.
 *
 * Error payload shape:
 * {
 *   "error": {
 *     "code":    HTTP status code (int),
 *     "message": human-readable description (string),
 *     ...extra context fields from ApiException::getContext()
 *   }
 * }
 */
final class ExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $appEnvironment,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        // Let higher-priority listeners (e.g. Symfony's security ExceptionListener at priority 1)
        // handle the exception first. If they already set a response (e.g. JWT 401 for anonymous
        // users), do not override it.
        if ($event->hasResponse()) {
            return;
        }

        $exception = $event->getThrowable();
        $response  = $this->buildResponse($exception);

        $event->setResponse($response);
    }

    private function buildResponse(\Throwable $exception): JsonResponse
    {
        if ($exception instanceof ApiException) {
            $payload = [
                'code'    => $exception->getStatusCode(),
                'message' => $exception->getMessage(),
                ...$exception->getContext(),
            ];

            return new JsonResponse(['error' => $payload], $exception->getStatusCode());
        }

        if ($exception instanceof AccessDeniedException) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_FORBIDDEN, 'message' => 'Access denied.']],
                Response::HTTP_FORBIDDEN,
            );
        }

        if ($exception instanceof AuthenticationException) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_UNAUTHORIZED, 'message' => 'Authentication required.']],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if ($exception instanceof HttpExceptionInterface) {
            return new JsonResponse(
                ['error' => ['code' => $exception->getStatusCode(), 'message' => $exception->getMessage()]],
                $exception->getStatusCode(),
            );
        }

        // Unexpected error — log it, return 500
        $this->logger->error('Unhandled exception', [
            'exception' => $exception::class,
            'message'   => $exception->getMessage(),
            'trace'     => $exception->getTraceAsString(),
        ]);

        $message = $this->appEnvironment === 'prod'
            ? 'An unexpected error occurred. Please try again later.'
            : $exception->getMessage();

        return new JsonResponse(
            ['error' => ['code' => Response::HTTP_INTERNAL_SERVER_ERROR, 'message' => $message]],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
