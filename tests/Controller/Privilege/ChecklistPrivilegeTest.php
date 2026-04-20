<?php

declare(strict_types=1);

namespace App\Tests\Controller\Privilege;

use App\Household\Entity\HouseholdPrivilege;
use App\Todo\Entity\Checklist;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers Checklist controller privilege gates.
 *
 * Voter attributes involved:
 * - MANAGE_CHECKLISTS (moderator+): rename, move, delete, add
 * - EDIT_CHECKLISTS (any member): update, subscribe, unsubscribe
 */
class ChecklistPrivilegeTest extends WebTestCase
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

    // --- add checklist (MANAGE_CHECKLISTS) ---

    public function testModeratorCanAddChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);

        $this->client->loginUser($moderator);
        $this->client->request('PUT', "/api/household/{$household->getId()}/checklist/add");

        $this->assertResponseIsSuccessful();
    }

    public function testRegularMemberCannotAddChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($member);
        $this->client->request('PUT', "/api/household/{$household->getId()}/checklist/add");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotAddChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request('PUT', "/api/household/{$household->getId()}/checklist/add");

        $this->assertResponseStatusCodeSame(403);
    }

    // --- rename checklist (MANAGE_CHECKLISTS) ---

    public function testModeratorCanRenameChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($moderator);
        $this->client->request(
            'POST',
            "/api/household/checklist/{$checklist->getUuid()}/rename",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Renamed'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testRegularMemberCannotRenameChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            "/api/household/checklist/{$checklist->getUuid()}/rename",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Renamed'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotRenameChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($outsider);
        $this->client->request(
            'POST',
            "/api/household/checklist/{$checklist->getUuid()}/rename",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Renamed'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- move checklist (MANAGE_CHECKLISTS) ---

    public function testModeratorCanMoveChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($moderator);
        $this->client->request(
            'POST',
            "/api/household/checklist/{$checklist->getUuid()}/move",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['moveAfterUuid' => null], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testRegularMemberCannotMoveChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            "/api/household/checklist/{$checklist->getUuid()}/move",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['moveAfterUuid' => null], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- delete checklist (MANAGE_CHECKLISTS) ---

    public function testModeratorCanDeleteChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);
        $checklist = $this->createChecklist($household);
        $uuid = $checklist->getUuid();

        $this->client->loginUser($moderator);
        $this->client->request('DELETE', "/api/household/checklist/{$uuid}");

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->em->find(Checklist::class, $uuid));
    }

    public function testRegularMemberCannotDeleteChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($member);
        $this->client->request('DELETE', "/api/household/checklist/{$checklist->getUuid()}");

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->em->find(Checklist::class, $checklist->getUuid()));
    }

    public function testNonMemberCannotDeleteChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($outsider);
        $this->client->request('DELETE', "/api/household/checklist/{$checklist->getUuid()}");

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->em->find(Checklist::class, $checklist->getUuid()));
    }

    // --- update checklist (EDIT_CHECKLISTS — any member) ---

    public function testRegularMemberCanUpdateChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            "/api/household/checklist/{$checklist->getUuid()}/update",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['events' => []], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotUpdateChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($outsider);
        $this->client->request(
            'POST',
            "/api/household/checklist/{$checklist->getUuid()}/update",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['events' => []], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- subscribe / unsubscribe (EDIT_CHECKLISTS — any member) ---

    public function testRegularMemberCanSubscribeToChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($member);
        $this->client->request('POST', "/api/household/checklist/{$checklist->getUuid()}/subscribe");

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotSubscribeToChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($outsider);
        $this->client->request('POST', "/api/household/checklist/{$checklist->getUuid()}/subscribe");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRegularMemberCanUnsubscribeFromChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($member);
        $this->client->request('POST', "/api/household/checklist/{$checklist->getUuid()}/unsubscribe");

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotUnsubscribeFromChecklist(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $checklist = $this->createChecklist($household);

        $this->client->loginUser($outsider);
        $this->client->request('POST', "/api/household/checklist/{$checklist->getUuid()}/unsubscribe");

        $this->assertResponseStatusCodeSame(403);
    }
}
