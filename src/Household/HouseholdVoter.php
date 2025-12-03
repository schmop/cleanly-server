<?php

namespace App\Household;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdPrivilege;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Household>
 */
class HouseholdVoter extends Voter
{
    public const string MANAGE_TASKS = "manage_tasks";
    public const string MANAGE_HOUSEHOLD = "manage_household";
    public const string MANAGE_CHECKLISTS = "manage_checklists";
    public const string EDIT_CHECKLISTS = "edit_checklists";
    public const string ADD_FINANCE_TRANSACTIONS = "add_finance_transactions";
    public const string READ_HOUSEHOLD_CONTENTS = "read_household_contents";

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Household && in_array($attribute, [
                self::MANAGE_HOUSEHOLD,
                self::MANAGE_TASKS,
                self::MANAGE_CHECKLISTS,
                self::EDIT_CHECKLISTS,
                self::ADD_FINANCE_TRANSACTIONS,
                self::READ_HOUSEHOLD_CONTENTS,
            ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        assert($user instanceof User);
        try {
            $privilege = $subject->getUserPrivilege($user);
        } catch (NotInHouseholdException) {
            return false;
        }

        return match ($attribute) {
            self::MANAGE_HOUSEHOLD => $privilege === HouseholdPrivilege::PRIVILEGE_ADMIN,
            self::MANAGE_TASKS, self::MANAGE_CHECKLISTS => $privilege >= HouseholdPrivilege::PRIVILEGE_MODERATOR,
            self::EDIT_CHECKLISTS, self::ADD_FINANCE_TRANSACTIONS, self::READ_HOUSEHOLD_CONTENTS => true,
            default => false,
        };
    }
}
