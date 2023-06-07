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
    public const MANAGE_HOUSEHOLD = "manage_household";
    public const MANAGE_CHECKLISTS = "manage_checklists";
    public const EDIT_CHECKLISTS = "edit_checklists";

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Household && in_array($attribute, [self::MANAGE_HOUSEHOLD, self::MANAGE_TASKS]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        assert($user instanceof User);
        assert($subject instanceof Household);
        try {
            $privilege = $subject->getUserPrivilege($user);
        } catch (NotInHouseholdException) {
            return false;
        }
        return match ($attribute) {
            self::MANAGE_HOUSEHOLD => $privilege === HouseholdPrivilege::PRIVILEGE_ADMIN,
            self::MANAGE_TASKS, self::MANAGE_CHECKLISTS => $privilege >= HouseholdPrivilege::PRIVILEGE_MODERATOR,
            self::EDIT_CHECKLISTS => true,
            default => false,
        };
    }
}
