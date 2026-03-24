<?php

declare(strict_types=1);

namespace App\Shared\Security\Voter;

use App\Shared\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, null>
 */
final class InventoryVoter extends Voter
{
    public const VIEW   = 'inventory.view';
    public const ADJUST = 'inventory.adjust';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::ADJUST], strict: true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$token->getUser() instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW   => $this->security->isGranted('ROLE_VIEWER'),
            self::ADJUST => $this->security->isGranted('ROLE_MANAGER'),
            default      => false,
        };
    }
}
