<?php

declare(strict_types=1);

namespace App\Tests\Controller\Privilege;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdPrivilege;
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

    /**
     * @return array{User, Household}
     */
    private function makeHouseholdWithAdmin(): array
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);

        return [$admin, $household];
    }
}
