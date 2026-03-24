<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Module\Inventory\Application\Command\AdjustStockCommand;
use App\Module\Inventory\Application\Command\AdjustStockCommandHandler;
use App\Module\Inventory\Application\Query\GetInventoryQueryHandler;
use App\Module\Inventory\Application\Query\ListInventoryQueryHandler;
use App\Shared\Exception\ValidationException;
use App\Shared\Security\Voter\InventoryVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/inventory', name: 'api_inventory_')]
final class InventoryController extends AbstractController
{
    public function __construct(
        private readonly ListInventoryQueryHandler $listQueryHandler,
        private readonly GetInventoryQueryHandler $getQueryHandler,
        private readonly AdjustStockCommandHandler $adjustHandler,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * GET /api/v1/inventory
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::VIEW);

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 25)));

        return new JsonResponse($this->listQueryHandler->handle($page, $limit));
    }

    /**
     * GET /api/v1/inventory/low-stock
     */
    #[Route('/low-stock', name: 'low_stock', methods: ['GET'])]
    public function lowStock(): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ADJUST);

        $items = $this->listQueryHandler->handleLowStock();

        return new JsonResponse(['data' => $items]);
    }

    /**
     * GET /api/v1/inventory/{productUuid}
     */
    #[Route('/{productUuid}', name: 'get', methods: ['GET'])]
    public function get(string $productUuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::VIEW);

        $response = $this->getQueryHandler->handle($productUuid);

        return new JsonResponse(['data' => $response]);
    }

    /**
     * POST /api/v1/inventory/{productUuid}/adjust
     * Body: { quantity: int (positive=increase, negative=decrease), reason: string }
     */
    #[Route('/{productUuid}/adjust', name: 'adjust', methods: ['POST'])]
    public function adjust(string $productUuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ADJUST);

        $data    = json_decode($request->getContent(), true) ?? [];
        $command = new AdjustStockCommand(
            productUuid: $productUuid,
            quantity:    (int) ($data['quantity'] ?? 0),
            reason:      (string) ($data['reason'] ?? ''),
        );

        $violations = $this->validator->validate($command);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $response = $this->adjustHandler->handle($command);

        return new JsonResponse(['data' => $response]);
    }
}
