<?php

declare(strict_types=1);

namespace App\Tests\Controller\Privilege;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdInvite;
use App\Household\Entity\HouseholdPrivilege;
use App\Household\ReassignmentStrategy;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers MANAGE_HOUSEHOLD (admin-only) endpoints.
 *
 * Voter: HouseholdVoter::MANAGE_HOUSEHOLD — admin only.
 * Expected denial response: HTTP 403.
 */
class HouseholdManagePrivilegeTest extends WebTestCase
{
    use PrivilegeFixtureTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->initFixtures();
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupFixtures();
        } finally {
            parent::tearDown();
        }
    }

    // --- invite ---

    public function testAdminCanInvite(): void
    {
        [$admin, $household] = $this->makeHouseholdWithAdmin();
        $invitee = $this->createUser('invitee');

        $this->client->loginUser($admin);
        $this->client->request(
            'POST',
            "/api/household/invite/{$household->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['ids' => [$invitee->getId()]], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();

        $reloaded = $this->refetch(Household::class, $household->getId());
        $this->assertNotNull($reloaded);
        $invitedIds = array_map(
            static fn (HouseholdInvite $i): ?int => $i->getInvitee()?->getId(),
            $reloaded->getInvites()->toArray(),
        );
        $this->assertContains($invitee->getId(), $invitedIds);
    }

    public function testModeratorCannotInvite(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);
        $invitee = $this->createUser('invitee');

        $this->client->loginUser($moderator);
        $this->client->request(
            'POST',
            "/api/household/invite/{$household->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['ids' => [$invitee->getId()]], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMemberCannotInvite(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $invitee = $this->createUser('invitee');

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            "/api/household/invite/{$household->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['ids' => [$invitee->getId()]], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotInvite(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $outsider = $this->createUser('outsider');
        $invitee = $this->createUser('invitee');

        $this->client->loginUser($outsider);
        $this->client->request(
            'POST',
            "/api/household/invite/{$household->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['ids' => [$invitee->getId()]], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- kick ---

    public function testAdminCanKickRegularMember(): void
    {
        [$admin, $household] = $this->makeHouseholdWithAdmin();
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($admin);
        $this->client->request('POST', "/api/household/kick/{$household->getId()}/{$member->getId()}");

        $this->assertResponseIsSuccessful();

        $reloaded = $this->refetch(Household::class, $household->getId());
        $this->assertNotNull($reloaded);
        $memberIds = array_map(
            static fn (User $u): ?int => $u->getId(),
            $reloaded->getMembers()->toArray(),
        );
        $this->assertNotContains($member->getId(), $memberIds);
    }

    public function testModeratorCannotKick(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($moderator);
        $this->client->request('POST', "/api/household/kick/{$household->getId()}/{$member->getId()}");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMemberCannotKick(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $other = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($member);
        $this->client->request('POST', "/api/household/kick/{$household->getId()}/{$other->getId()}");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotKick(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request('POST', "/api/household/kick/{$household->getId()}/{$member->getId()}");

        $this->assertResponseStatusCodeSame(403);
    }

    // --- change privilege ---

    public function testAdminCanChangeMemberPrivilege(): void
    {
        [$admin, $household] = $this->makeHouseholdWithAdmin();
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($admin);
        $this->client->request(
            'POST',
            sprintf('/api/household/privilege/%d/%d/%d', $household->getId(), $member->getId(), HouseholdPrivilege::PRIVILEGE_MODERATOR),
        );

        $this->assertResponseIsSuccessful();

        $reloaded = $this->refetch(Household::class, $household->getId());
        $this->assertNotNull($reloaded);
        $reloadedMember = $this->memberOf($reloaded, $member->getId());
        $this->assertSame(
            HouseholdPrivilege::PRIVILEGE_MODERATOR,
            $reloaded->getUserPrivilege($reloadedMember),
        );
    }

    public function testModeratorCannotChangePrivilege(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($moderator);
        $this->client->request(
            'POST',
            sprintf('/api/household/privilege/%d/%d/%d', $household->getId(), $member->getId(), HouseholdPrivilege::PRIVILEGE_MODERATOR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotChangePrivilege(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request(
            'POST',
            sprintf('/api/household/privilege/%d/%d/%d', $household->getId(), $member->getId(), HouseholdPrivilege::PRIVILEGE_MODERATOR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- delete household ---

    public function testAdminCanDeleteHousehold(): void
    {
        [$admin, $household] = $this->makeHouseholdWithAdmin();
        $householdId = $household->getId();

        $this->client->loginUser($admin);
        $this->client->request('DELETE', "/api/household/{$householdId}");

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->em->find(Household::class, $householdId));
    }

    public function testModeratorCannotDeleteHousehold(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);

        $this->client->loginUser($moderator);
        $this->client->request('DELETE', "/api/household/{$household->getId()}");

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->em->find(Household::class, $household->getId()));
    }

    public function testNonMemberCannotDeleteHousehold(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request('DELETE', "/api/household/{$household->getId()}");

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->em->find(Household::class, $household->getId()));
    }

    // --- set webhook ---

    public function testAdminCanSetWebhook(): void
    {
        [$admin, $household] = $this->makeHouseholdWithAdmin();

        $this->client->loginUser($admin);
        $this->client->request(
            'POST',
            "/api/household/webhook/{$household->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['webhook_url' => 'https://example.invalid'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();

        $reloaded = $this->refetch(Household::class, $household->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('https://example.invalid', $reloaded->getWebhookUrl());
    }

    public function testModeratorCannotSetWebhook(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);

        $this->client->loginUser($moderator);
        $this->client->request(
            'POST',
            "/api/household/webhook/{$household->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['webhook_url' => 'https://example.invalid'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotSetWebhook(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request(
            'POST',
            "/api/household/webhook/{$household->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['webhook_url' => 'https://example.invalid'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- set reassignment strategy ---

    public function testAdminCanSetReassignmentStrategy(): void
    {
        [$admin, $household] = $this->makeHouseholdWithAdmin();

        $this->client->loginUser($admin);
        $this->client->request(
            'POST',
            "/api/household/reassignment-strategy/{$household->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reassignmentStrategy' => 'rotate'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();

        $reloaded = $this->refetch(Household::class, $household->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(ReassignmentStrategy::Rotate, $reloaded->getReassignmentStrategy());
    }

    public function testModeratorCannotSetReassignmentStrategy(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);

        $this->client->loginUser($moderator);
        $this->client->request(
            'POST',
            "/api/household/reassignment-strategy/{$household->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reassignmentStrategy' => 'rotate'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotSetReassignmentStrategy(): void
    {
        [, $household] = $this->makeHouseholdWithAdmin();
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request(
            'POST',
            "/api/household/reassignment-strategy/{$household->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reassignmentStrategy' => 'rotate'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- change privilege: request-validation guards (all reject with 400) ---

    public function testAdminCannotChangeTheirOwnPrivilege(): void
    {
        [$admin, $household] = $this->makeHouseholdWithAdmin();

        $this->client->loginUser($admin);
        $this->client->request(
            'POST',
            sprintf('/api/household/privilege/%d/%d/%d', $household->getId(), $admin->getId(), HouseholdPrivilege::PRIVILEGE_USER),
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('You cannot change your own privileges!', $this->errorReason());

        // The admin must keep admin rights, or they could lock themselves out.
        $reloaded = $this->refetch(Household::class, $household->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(
            HouseholdPrivilege::PRIVILEGE_ADMIN,
            $reloaded->getUserPrivilege($this->memberOf($reloaded, $admin->getId())),
        );
    }

    public function testCannotChangePrivilegeOfNonMember(): void
    {
        [$admin, $household] = $this->makeHouseholdWithAdmin();
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($admin);
        $this->client->request(
            'POST',
            sprintf('/api/household/privilege/%d/%d/%d', $household->getId(), $outsider->getId(), HouseholdPrivilege::PRIVILEGE_MODERATOR),
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString("aren't members", $this->errorReason());

        // No membership may be created as a side effect of the rejected call.
        $reloaded = $this->refetch(Household::class, $household->getId());
        $this->assertNotNull($reloaded);
        $memberIds = array_map(static fn (User $u): ?int => $u->getId(), $reloaded->getMembers()->toArray());
        $this->assertNotContains($outsider->getId(), $memberIds);
    }

    public function testInvalidPrivilegeLevelIsRejected(): void
    {
        [$admin, $household] = $this->makeHouseholdWithAdmin();
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($admin);
        $this->client->request(
            'POST',
            sprintf('/api/household/privilege/%d/%d/%d', $household->getId(), $member->getId(), 9999),
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Invalid privilege given!', $this->errorReason());

        $reloaded = $this->refetch(Household::class, $household->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(
            HouseholdPrivilege::PRIVILEGE_USER,
            $reloaded->getUserPrivilege($this->memberOf($reloaded, $member->getId())),
        );
    }

    public function testAdminCannotDemoteAnotherAdmin(): void
    {
        [$admin, $household] = $this->makeHouseholdWithAdmin();
        $coAdmin = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_ADMIN);

        $this->client->loginUser($admin);
        $this->client->request(
            'POST',
            sprintf('/api/household/privilege/%d/%d/%d', $household->getId(), $coAdmin->getId(), HouseholdPrivilege::PRIVILEGE_USER),
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('You cannot overthrow another admin!', $this->errorReason());

        $reloaded = $this->refetch(Household::class, $household->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(
            HouseholdPrivilege::PRIVILEGE_ADMIN,
            $reloaded->getUserPrivilege($this->memberOf($reloaded, $coAdmin->getId())),
        );
    }

    /**
     * @return array{User, Household}
     */
    private function makeHouseholdWithAdmin(): array
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);

        return [$admin, $household];
    }

    /** Resolves a member on a freshly loaded household; privileges match by identity. */
    private function memberOf(Household $household, ?int $userId): User
    {
        foreach ($household->getMembers() as $member) {
            if ($member->getId() === $userId) {
                return $member;
            }
        }

        self::fail("User {$userId} is not a member of the household");
    }

    private function errorReason(): string
    {
        $payload = json_decode(
            (string)$this->client->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($payload);
        self::assertArrayHasKey('reason', $payload);
        self::assertIsString($payload['reason']);

        return $payload['reason'];
    }
}
