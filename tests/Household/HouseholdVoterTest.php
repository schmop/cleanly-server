<?php

declare(strict_types=1);

namespace App\Tests\Household;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdPrivilege;
use App\Household\HouseholdVoter;
use App\Household\ReassignmentStrategy;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class HouseholdVoterTest extends TestCase
{
    private HouseholdVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new HouseholdVoter();
    }

    public function testAbstainsForUnknownAttribute(): void
    {
        $member = $this->newUser('member@test');
        $household = $this->newHousehold($member, HouseholdPrivilege::PRIVILEGE_USER);

        $result = $this->voter->vote($this->tokenFor($member), $household, ['unknown_action']);
        $this->assertSame(Voter::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForNonHouseholdSubject(): void
    {
        $member = $this->newUser('member@test');
        $result = $this->voter->vote(
            $this->tokenFor($member),
            new \stdClass(),
            [HouseholdVoter::READ_HOUSEHOLD_CONTENTS],
        );
        $this->assertSame(Voter::ACCESS_ABSTAIN, $result);
    }

    public function testNonMemberIsDeniedEverything(): void
    {
        $member = $this->newUser('member@test');
        $outsider = $this->newUser('outsider@test');
        $household = $this->newHousehold($member, HouseholdPrivilege::PRIVILEGE_ADMIN);

        foreach ([
            HouseholdVoter::MANAGE_HOUSEHOLD,
            HouseholdVoter::MANAGE_TASKS,
            HouseholdVoter::MANAGE_CHECKLISTS,
            HouseholdVoter::EDIT_CHECKLISTS,
            HouseholdVoter::ADD_FINANCE_TRANSACTIONS,
            HouseholdVoter::READ_HOUSEHOLD_CONTENTS,
        ] as $attribute) {
            $this->assertSame(
                Voter::ACCESS_DENIED,
                $this->voter->vote($this->tokenFor($outsider), $household, [$attribute]),
                "outsider should be denied $attribute",
            );
        }
    }

    public function testAdminCanDoEverything(): void
    {
        $admin = $this->newUser('admin@test');
        $household = $this->newHousehold($admin, HouseholdPrivilege::PRIVILEGE_ADMIN);

        foreach ([
            HouseholdVoter::MANAGE_HOUSEHOLD,
            HouseholdVoter::MANAGE_TASKS,
            HouseholdVoter::MANAGE_CHECKLISTS,
            HouseholdVoter::EDIT_CHECKLISTS,
            HouseholdVoter::ADD_FINANCE_TRANSACTIONS,
            HouseholdVoter::READ_HOUSEHOLD_CONTENTS,
        ] as $attribute) {
            $this->assertSame(
                Voter::ACCESS_GRANTED,
                $this->voter->vote($this->tokenFor($admin), $household, [$attribute]),
                "admin should be granted $attribute",
            );
        }
    }

    public function testModeratorCanManageTasksAndChecklistsButNotHousehold(): void
    {
        $admin = $this->newUser('admin@test');
        $household = $this->newHousehold($admin, HouseholdPrivilege::PRIVILEGE_ADMIN);
        $moderator = $this->addMember($household, 'mod@test', HouseholdPrivilege::PRIVILEGE_MODERATOR);
        $token = $this->tokenFor($moderator);

        $this->assertSame(Voter::ACCESS_GRANTED, $this->voter->vote($token, $household, [HouseholdVoter::MANAGE_TASKS]));
        $this->assertSame(Voter::ACCESS_GRANTED, $this->voter->vote($token, $household, [HouseholdVoter::MANAGE_CHECKLISTS]));
        $this->assertSame(Voter::ACCESS_DENIED, $this->voter->vote($token, $household, [HouseholdVoter::MANAGE_HOUSEHOLD]));
    }

    public function testRegularMemberCannotManageButCanReadAndAddFinances(): void
    {
        $admin = $this->newUser('admin@test');
        $household = $this->newHousehold($admin, HouseholdPrivilege::PRIVILEGE_ADMIN);
        $regular = $this->addMember($household, 'member@test', HouseholdPrivilege::PRIVILEGE_USER);
        $token = $this->tokenFor($regular);

        $this->assertSame(Voter::ACCESS_DENIED, $this->voter->vote($token, $household, [HouseholdVoter::MANAGE_HOUSEHOLD]));
        $this->assertSame(Voter::ACCESS_DENIED, $this->voter->vote($token, $household, [HouseholdVoter::MANAGE_TASKS]));
        $this->assertSame(Voter::ACCESS_DENIED, $this->voter->vote($token, $household, [HouseholdVoter::MANAGE_CHECKLISTS]));
        $this->assertSame(Voter::ACCESS_GRANTED, $this->voter->vote($token, $household, [HouseholdVoter::EDIT_CHECKLISTS]));
        $this->assertSame(Voter::ACCESS_GRANTED, $this->voter->vote($token, $household, [HouseholdVoter::ADD_FINANCE_TRANSACTIONS]));
        $this->assertSame(Voter::ACCESS_GRANTED, $this->voter->vote($token, $household, [HouseholdVoter::READ_HOUSEHOLD_CONTENTS]));
    }

    // -- helpers --

    private function newUser(string $email): User
    {
        $user = new User($email, 'Test User');
        $user->setPassword('irrelevant');
        return $user;
    }

    private function newHousehold(User $admin, int $adminPrivilege): Household
    {
        $household = new Household();
        $household->setName('Voter Test Household');
        $household->setReassignmentStrategy(ReassignmentStrategy::None);
        $household->addMember($admin);
        $household->setUserPrivilege($admin, $adminPrivilege);
        return $household;
    }

    private function addMember(Household $household, string $email, int $privilege): User
    {
        $user = $this->newUser($email);
        $household->addMember($user);
        $household->setUserPrivilege($user, $privilege);
        return $user;
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }
}
