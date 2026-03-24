<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Module\Product\Application\Query\CategoryResponse;
use App\Module\Product\Domain\Category;
use App\Module\Product\Domain\CategoryRepositoryInterface;
use App\Module\Product\Domain\Exception\CategoryNotFoundException;
use App\Shared\Security\Voter\ProductVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/categories', name: 'api_categories_')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::VIEW);

        $categories = array_map(
            CategoryResponse::fromEntity(...),
            $this->categoryRepository->findAll(),
        );

        return new JsonResponse(['data' => $categories]);
    }

    #[Route('/{uuid}', name: 'get', methods: ['GET'])]
    public function get(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::VIEW);

        $category = $this->categoryRepository->findByUuid($uuid);

        if ($category === null) {
            throw new CategoryNotFoundException($uuid);
        }

        return new JsonResponse(['data' => CategoryResponse::fromEntity($category)]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::CREATE);

        $data = json_decode($request->getContent(), true) ?? [];
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return new JsonResponse(
                ['error' => ['code' => 422, 'message' => 'Category name is required.']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($this->categoryRepository->findByName($name) !== null) {
            return new JsonResponse(
                ['error' => ['code' => 409, 'message' => "Category \"{$name}\" already exists."]],
                Response::HTTP_CONFLICT,
            );
        }

        $category = new Category($name, $data['description'] ?? null);
        $this->categoryRepository->save($category, flush: true);

        return new JsonResponse(
            ['data' => CategoryResponse::fromEntity($category)],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{uuid}', name: 'update', methods: ['PUT'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::UPDATE);

        $category = $this->categoryRepository->findByUuid($uuid);

        if ($category === null) {
            throw new CategoryNotFoundException($uuid);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return new JsonResponse(
                ['error' => ['code' => 422, 'message' => 'Category name is required.']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $category->update($name, $data['description'] ?? null);
        $this->categoryRepository->save($category, flush: true);

        return new JsonResponse(['data' => CategoryResponse::fromEntity($category)]);
    }

    #[Route('/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::DELETE);

        $category = $this->categoryRepository->findByUuid($uuid);

        if ($category === null) {
            throw new CategoryNotFoundException($uuid);
        }

        $this->categoryRepository->remove($category, flush: true);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
