<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\Address;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Voter Symfony qui autorise la modification ou la suppression d'une adresse uniquement par son propriétaire.
 */
final class AddressVoter extends Voter
{
    public const EDIT = 'ADDRESS_EDIT';
    public const DELETE = 'ADDRESS_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE], true)
            && $subject instanceof Address;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Address $address */
        $address = $subject;

        $owner = $address->getUser();
        if (!$owner instanceof User) {
            return false;
        }

        return $owner->getId() === $user->getId();
    }
}
