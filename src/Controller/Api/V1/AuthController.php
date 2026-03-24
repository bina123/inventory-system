<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Shared\Entity\User;
use App\Shared\Exception\ValidationException;
use App\Shared\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/auth', name: 'api_auth_')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Login endpoint — handled entirely by the json_login firewall authenticator.
     * This method is never reached; the firewall intercepts the request first
     * and delegates to LexikJWTAuthenticationBundle's success/failure handlers.
     *
     * POST /api/v1/auth/login
     * Body: { "email": "...", "password": "..." }
     * Response: { "token": "<jwt>" }
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('The firewall should have intercepted this request before it reached the controller.');
    }

    /**
     * Register a new user account.
     *
     * POST /api/v1/auth/register
     * Body: { email, password, fullName, role? }
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $email    = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $fullName = trim((string) ($data['fullName'] ?? ''));
        $role     = (string) ($data['role'] ?? 'ROLE_VIEWER');

        if ($this->userRepository->findByEmail($email) !== null) {
            return new JsonResponse(
                ['error' => ['code' => 409, 'message' => 'Email already registered.']],
                Response::HTTP_CONFLICT,
            );
        }

        $allowedRoles = ['ROLE_VIEWER', 'ROLE_MANAGER', 'ROLE_ADMIN'];

        if (!in_array($role, $allowedRoles, strict: true)) {
            $role = 'ROLE_VIEWER';
        }

        $user = new User($email, $fullName);
        $user->setRoles([$role]);

        $violations = $this->validator->validate($user);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        if (strlen($password) < 8) {
            return new JsonResponse(
                ['error' => ['code' => 422, 'message' => 'Password must be at least 8 characters.']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->userRepository->save($user, flush: true);

        return new JsonResponse([
            'data' => [
                'uuid'      => $user->getUuid(),
                'email'     => $user->getEmail(),
                'fullName'  => $user->getFullName(),
                'roles'     => $user->getRoles(),
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Get the currently authenticated user's profile.
     *
     * GET /api/v1/auth/me
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse([
            'data' => [
                'uuid'      => $user->getUuid(),
                'email'     => $user->getEmail(),
                'fullName'  => $user->getFullName(),
                'roles'     => $user->getRoles(),
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }
}
