<?php

declare(strict_types=1);

namespace App\Shared\Security\Voter;

use App\Module\Order\Domain\Order;
use App\Shared\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Order|null>
 */
final class OrderVoter extends Voter
{
    public const VIEW   = 'order.view';
    public const PLACE  = 'order.place';
    public const CANCEL = 'order.cancel';
    public const FULFIL = 'order.fulfil';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::PLACE, self::CANCEL, self::FULFIL], strict: true)
            && ($subject === null || $subject instanceof Order);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$token->getUser() instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW   => $this->security->isGranted('ROLE_MANAGER'),
            self::PLACE  => $this->security->isGranted('ROLE_MANAGER'),
            self::CANCEL => $this->security->isGranted('ROLE_MANAGER'),
            self::FULFIL => $this->security->isGranted('ROLE_ADMIN'),
            default      => false,
        };
    }
}
