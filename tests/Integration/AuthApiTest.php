<?php

declare(strict_types=1);

namespace App\Tests\Integration;

final class AuthApiTest extends ApiTestCase
{
    public function test_register_creates_user_and_returns_201(): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email'    => 'newuser@example.com',
            'password' => 'Password123!',
            'fullName' => 'New User',
            'role'     => 'ROLE_VIEWER',
        ]);

        $this->assertStatus(201);
        $data = $this->json()['data'];
        self::assertSame('newuser@example.com', $data['email']);
        self::assertContains('ROLE_VIEWER', $data['roles']);
        self::assertArrayHasKey('uuid', $data);
    }

    public function test_register_duplicate_email_returns_409(): void
    {
        $this->createUser('dup@example.com', 'Password123!');

        $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email'    => 'dup@example.com',
            'password' => 'Password123!',
            'fullName' => 'Dup User',
        ]);

        $this->assertStatus(409);
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $this->createUser('login@example.com', 'Password123!');

        $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email'    => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $this->assertStatus(200);
        self::assertArrayHasKey('token', $this->json());
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        $this->createUser('secure@example.com', 'CorrectPassword!');

        $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email'    => 'secure@example.com',
            'password' => 'WrongPassword!',
        ]);

        $this->assertStatus(401);
    }

    public function test_me_returns_authenticated_user_profile(): void
    {
        $this->createUser('me@example.com', 'Password123!', 'ROLE_ADMIN');
        $token = $this->getToken('me@example.com', 'Password123!');

        $this->jsonRequest('GET', '/api/v1/auth/me', token: $token);

        $this->assertStatus(200);
        $data = $this->json()['data'];
        self::assertSame('me@example.com', $data['email']);
    }

    public function test_me_without_token_returns_401(): void
    {
        $this->jsonRequest('GET', '/api/v1/auth/me');

        $this->assertStatus(401);
    }
}
