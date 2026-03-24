<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Shared\Entity\User;
use App\Shared\Security\Voter\ProductVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ProductVoterTest extends TestCase
{
    private Security&MockObject $security;
    private ProductVoter $voter;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->voter    = new ProductVoter($this->security);
    }

    private function makeToken(string ...$roles): UsernamePasswordToken
    {
        $user = new User('test@example.com', 'Test User');
        $user->setRoles($roles);

        return new UsernamePasswordToken($user, 'test', $roles);
    }

    public function test_viewer_can_view_products(): void
    {
        $this->security->method('isGranted')->with('ROLE_VIEWER')->willReturn(true);

        $result = $this->voter->vote($this->makeToken('ROLE_VIEWER'), null, [ProductVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function test_viewer_cannot_create_products(): void
    {
        $this->security->method('isGranted')->with('ROLE_MANAGER')->willReturn(false);

        $result = $this->voter->vote($this->makeToken('ROLE_VIEWER'), null, [ProductVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function test_manager_can_create_products(): void
    {
        $this->security->method('isGranted')->with('ROLE_MANAGER')->willReturn(true);

        $result = $this->voter->vote($this->makeToken('ROLE_MANAGER'), null, [ProductVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function test_admin_inherits_viewer_permission_via_hierarchy(): void
    {
        // Security::isGranted() respects the hierarchy, so ROLE_ADMIN satisfies ROLE_VIEWER
        $this->security->method('isGranted')->with('ROLE_VIEWER')->willReturn(true);

        $result = $this->voter->vote($this->makeToken('ROLE_ADMIN'), null, [ProductVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function test_manager_cannot_delete_products(): void
    {
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $result = $this->voter->vote($this->makeToken('ROLE_MANAGER'), null, [ProductVoter::DELETE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function test_admin_can_delete_products(): void
    {
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $result = $this->voter->vote($this->makeToken('ROLE_ADMIN'), null, [ProductVoter::DELETE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function test_unauthenticated_user_is_denied(): void
    {
        $token = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $result = $this->voter->vote($token, null, [ProductVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function test_unknown_attribute_is_abstained(): void
    {
        $result = $this->voter->vote($this->makeToken('ROLE_ADMIN'), null, ['product.unknown']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }
}
