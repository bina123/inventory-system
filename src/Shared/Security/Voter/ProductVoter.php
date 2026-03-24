<?php

declare(strict_types=1);

namespace App\Shared\Security\Voter;

use App\Module\Product\Domain\Product;
use App\Shared\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * RBAC voter for Product operations.
 *
 * Uses Security::isGranted() so the role hierarchy is respected:
 *   ROLE_ADMIN > ROLE_MANAGER > ROLE_VIEWER
 *
 * @extends Voter<string, Product|null>
 */
final class ProductVoter extends Voter
{
    public const VIEW   = 'product.view';
    public const CREATE = 'product.create';
    public const UPDATE = 'product.update';
    public const DELETE = 'product.delete';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::UPDATE, self::DELETE], strict: true)
            && ($subject === null || $subject instanceof Product);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$token->getUser() instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW   => $this->security->isGranted('ROLE_VIEWER'),
            self::CREATE => $this->security->isGranted('ROLE_MANAGER'),
            self::UPDATE => $this->security->isGranted('ROLE_MANAGER'),
            self::DELETE => $this->security->isGranted('ROLE_ADMIN'),
            default      => false,
        };
    }
}
