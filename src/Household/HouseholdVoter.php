<?php

namespace App\Household;
use App\Household\Entity\Household;
use App\Household\Entity\HouseholdPrivilege;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class HouseholdVoter extends Voter
{
    public const MANAGE_TASKS = "manage_tasks";
    public const MANAGE_HOUSEHOLD = "manage_tasks";

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Household && in_array($attribute, [self::MANAGE_HOUSEHOLD, self::MANAGE_TASKS]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        assert($user instanceof User);
        assert($subject instanceof Household);
        $privilege = $subject->getUserPrivilege($user);
        switch ($attribute) {
            case self::MANAGE_HOUSEHOLD:
                return $privilege === HouseholdPrivilege::PRIVILEGE_ADMIN;
            case self::MANAGE_TASKS:
                return $privilege >= HouseholdPrivilege::PRIVILEGE_MODERATOR;
        }

        return false;
    }
}
