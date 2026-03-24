<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Shared\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for all API integration tests.
 *
 * Provides helpers for authentication, JSON requests, and database cleanup.
 * Each test class gets a fresh database transaction that is rolled back after
 * each test — no data leaks between tests.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Prevent kernel reboot between requests within the same test.
        // Without this, the kernel (and EntityManager) is rebooted on every
        // request, creating a new DB connection that cannot see data written
        // inside our open transaction.
        $this->client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Wrap each test in a transaction and roll back after — fast and clean
        $this->em->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Auth helpers
    // -------------------------------------------------------------------------

    protected function createUser(string $email, string $password, string $role = 'ROLE_VIEWER'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user   = new User($email, 'Test User');
        $user->setRoles([$role]);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function getToken(string $email, string $password): string
    {
        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $email, 'password' => $password]));

        $data = $this->json();

        self::assertArrayHasKey('token', $data, 'Login did not return a token.');

        return $data['token'];
    }

    // -------------------------------------------------------------------------
    // HTTP helpers
    // -------------------------------------------------------------------------

    protected function jsonRequest(
        string $method,
        string $uri,
        array $body = [],
        ?string $token = null,
    ): void {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = "Bearer {$token}";
        }

        $this->client->request($method, $uri, [], [], $headers, $body !== [] ? json_encode($body) : null);
    }

    protected function json(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    protected function assertStatus(int $expected): void
    {
        self::assertSame(
            $expected,
            $this->client->getResponse()->getStatusCode(),
            'Response body: '.$this->client->getResponse()->getContent(),
        );
    }
}
