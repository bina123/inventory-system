<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Module\Product\Application\Command\CreateProductCommand;
use App\Module\Product\Application\Command\CreateProductCommandHandler;
use App\Module\Product\Application\Command\DeleteProductCommand;
use App\Module\Product\Application\Command\DeleteProductCommandHandler;
use App\Module\Product\Application\Command\UpdateProductCommand;
use App\Module\Product\Application\Command\UpdateProductCommandHandler;
use App\Module\Product\Application\Query\GetProductQuery;
use App\Module\Product\Application\Query\GetProductQueryHandler;
use App\Module\Product\Application\Query\ListProductsQuery;
use App\Module\Product\Application\Query\ListProductsQueryHandler;
use App\Shared\Exception\ValidationException;
use App\Shared\Security\Voter\ProductVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/products', name: 'api_products_')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ListProductsQueryHandler $listQueryHandler,
        private readonly GetProductQueryHandler $getQueryHandler,
        private readonly CreateProductCommandHandler $createHandler,
        private readonly UpdateProductCommandHandler $updateHandler,
        private readonly DeleteProductCommandHandler $deleteHandler,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * GET /api/v1/products
     * Query params: page, limit, categoryUuid, sku, active
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::VIEW);

        $query = new ListProductsQuery(
            page:         max(1, (int) $request->query->get('page', 1)),
            limit:        min(100, max(1, (int) $request->query->get('limit', 25))),
            categoryUuid: $request->query->get('categoryUuid'),
            sku:          $request->query->get('sku'),
            isActive:     $request->query->has('active')
                ? filter_var($request->query->get('active'), FILTER_VALIDATE_BOOLEAN)
                : null,
        );

        return new JsonResponse($this->listQueryHandler->handle($query));
    }

    /**
     * GET /api/v1/products/{uuid}
     */
    #[Route('/{uuid}', name: 'get', methods: ['GET'])]
    public function get(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::VIEW);

        $response = $this->getQueryHandler->handle(new GetProductQuery($uuid));

        return new JsonResponse(['data' => $response]);
    }

    /**
     * POST /api/v1/products
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::CREATE);

        $data    = json_decode($request->getContent(), true) ?? [];
        $command = new CreateProductCommand(
            name:         (string) ($data['name'] ?? ''),
            sku:          (string) ($data['sku'] ?? ''),
            price:        (float) ($data['price'] ?? 0),
            currency:     strtoupper((string) ($data['currency'] ?? 'USD')),
            categoryUuid: (string) ($data['categoryUuid'] ?? ''),
            description:  $data['description'] ?? null,
        );

        $violations = $this->validator->validate($command);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $response = $this->createHandler->handle($command);

        return new JsonResponse(['data' => $response], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/v1/products/{uuid}
     */
    #[Route('/{uuid}', name: 'update', methods: ['PUT'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::UPDATE);

        $data    = json_decode($request->getContent(), true) ?? [];
        $command = new UpdateProductCommand(
            productUuid:  $uuid,
            name:         (string) ($data['name'] ?? ''),
            price:        (float) ($data['price'] ?? 0),
            currency:     strtoupper((string) ($data['currency'] ?? 'USD')),
            categoryUuid: (string) ($data['categoryUuid'] ?? ''),
            description:  $data['description'] ?? null,
        );

        $violations = $this->validator->validate($command);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $response = $this->updateHandler->handle($command);

        return new JsonResponse(['data' => $response]);
    }

    /**
     * DELETE /api/v1/products/{uuid}
     * Soft-delete: marks product as inactive.
     */
    #[Route('/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::DELETE);

        $this->deleteHandler->handle(new DeleteProductCommand($uuid));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
