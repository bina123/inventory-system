<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Shared\Entity\User;
use App\Shared\Security\Voter\OrderVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class OrderVoterTest extends TestCase
{
    private Security&MockObject $security;
    private OrderVoter $voter;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->voter    = new OrderVoter($this->security);
    }

    private function makeToken(string ...$roles): UsernamePasswordToken
    {
        $user = new User('test@example.com', 'Test User');
        $user->setRoles($roles);

        return new UsernamePasswordToken($user, 'test', $roles);
    }

    public function test_viewer_cannot_view_orders(): void
    {
        $this->security->method('isGranted')->with('ROLE_MANAGER')->willReturn(false);

        $result = $this->voter->vote($this->makeToken('ROLE_VIEWER'), null, [OrderVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function test_manager_can_view_orders(): void
    {
        $this->security->method('isGranted')->with('ROLE_MANAGER')->willReturn(true);

        $result = $this->voter->vote($this->makeToken('ROLE_MANAGER'), null, [OrderVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function test_manager_can_place_orders(): void
    {
        $this->security->method('isGranted')->with('ROLE_MANAGER')->willReturn(true);

        $result = $this->voter->vote($this->makeToken('ROLE_MANAGER'), null, [OrderVoter::PLACE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function test_manager_can_cancel_orders(): void
    {
        $this->security->method('isGranted')->with('ROLE_MANAGER')->willReturn(true);

        $result = $this->voter->vote($this->makeToken('ROLE_MANAGER'), null, [OrderVoter::CANCEL]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function test_manager_cannot_fulfil_orders(): void
    {
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $result = $this->voter->vote($this->makeToken('ROLE_MANAGER'), null, [OrderVoter::FULFIL]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function test_admin_can_fulfil_orders(): void
    {
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $result = $this->voter->vote($this->makeToken('ROLE_ADMIN'), null, [OrderVoter::FULFIL]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }
}
