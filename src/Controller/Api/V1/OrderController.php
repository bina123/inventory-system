<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Module\Order\Application\Command\CancelOrderCommand;
use App\Module\Order\Application\Command\CancelOrderCommandHandler;
use App\Module\Order\Application\Command\FulfilOrderCommand;
use App\Module\Order\Application\Command\FulfilOrderCommandHandler;
use App\Module\Order\Application\Command\PlaceOrderCommand;
use App\Module\Order\Application\Command\PlaceOrderCommandHandler;
use App\Module\Order\Application\Query\GetOrderQueryHandler;
use App\Module\Order\Application\Query\ListOrdersQueryHandler;
use App\Shared\Exception\ValidationException;
use App\Shared\Security\Voter\OrderVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/orders', name: 'api_orders_')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly ListOrdersQueryHandler $listQueryHandler,
        private readonly GetOrderQueryHandler $getQueryHandler,
        private readonly PlaceOrderCommandHandler $placeHandler,
        private readonly CancelOrderCommandHandler $cancelHandler,
        private readonly FulfilOrderCommandHandler $fulfilHandler,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * GET /api/v1/orders
     * Query params: page, limit, status, customerEmail
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(OrderVoter::VIEW);

        $filters = [];

        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }

        if ($request->query->has('customerEmail')) {
            $filters['customerEmail'] = $request->query->get('customerEmail');
        }

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 25)));

        return new JsonResponse($this->listQueryHandler->handle($filters, $page, $limit));
    }

    /**
     * GET /api/v1/orders/{uuid}
     */
    #[Route('/{uuid}', name: 'get', methods: ['GET'])]
    public function get(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(OrderVoter::VIEW);

        $response = $this->getQueryHandler->handle($uuid);

        return new JsonResponse(['data' => $response]);
    }

    /**
     * POST /api/v1/orders
     * Body: { customerEmail, items: [{productUuid, quantity}], notes? }
     */
    #[Route('', name: 'place', methods: ['POST'])]
    public function place(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(OrderVoter::PLACE);

        $data    = json_decode($request->getContent(), true) ?? [];
        $command = new PlaceOrderCommand(
            customerEmail: (string) ($data['customerEmail'] ?? ''),
            items:         (array) ($data['items'] ?? []),
            notes:         $data['notes'] ?? null,
        );

        $violations = $this->validator->validate($command);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $response = $this->placeHandler->handle($command);

        return new JsonResponse(['data' => $response], Response::HTTP_CREATED);
    }

    /**
     * POST /api/v1/orders/{uuid}/cancel
     */
    #[Route('/{uuid}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(OrderVoter::CANCEL);

        $response = $this->cancelHandler->handle(new CancelOrderCommand($uuid));

        return new JsonResponse(['data' => $response]);
    }

    /**
     * POST /api/v1/orders/{uuid}/fulfil
     */
    #[Route('/{uuid}/fulfil', name: 'fulfil', methods: ['POST'])]
    public function fulfil(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(OrderVoter::FULFIL);

        $response = $this->fulfilHandler->handle(new FulfilOrderCommand($uuid));

        return new JsonResponse(['data' => $response]);
    }
}
