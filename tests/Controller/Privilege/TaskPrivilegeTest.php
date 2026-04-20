<?php

declare(strict_types=1);

namespace App\Tests\Controller\Privilege;

use App\Household\Entity\HouseholdPrivilege;
use App\Task\Entity\Task;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers Task controller privilege gates.
 *
 * Voter attributes involved:
 * - MANAGE_TASKS (moderator+): task edit, task delete, mark-done as another user
 * - READ_HOUSEHOLD_CONTENTS (any member): mark-done for self, log, stats, assign
 */
class TaskPrivilegeTest extends WebTestCase
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

    // --- create task (MANAGE_TASKS via TaskFactory) ---

    public function testAdminCanCreateTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);

        $this->client->loginUser($admin);
        $this->client->request(
            'POST',
            '/api/task/create',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'household_id' => $household->getId(),
                'name' => 'Some task',
                'stars' => 1,
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testModeratorCanCreateTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);

        $this->client->loginUser($moderator);
        $this->client->request(
            'POST',
            '/api/task/create',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'household_id' => $household->getId(),
                'name' => 'Some task',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testRegularMemberCannotCreateTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            '/api/task/create',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'household_id' => $household->getId(),
                'name' => 'Some task',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotCreateTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request(
            'POST',
            '/api/task/create',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'household_id' => $household->getId(),
                'name' => 'Some task',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- edit task (MANAGE_TASKS) ---

    public function testModeratorCanEditTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);
        $task = $this->createTask($household);

        $this->client->loginUser($moderator);
        $this->client->request(
            'POST',
            "/api/task/edit/{$task->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Renamed',
                'icon' => 'cat',
                'hue' => null,
                'duration' => null,
                'stars' => 2,
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testAdminCanEditTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $task = $this->createTask($household);

        $this->client->loginUser($admin);
        $this->client->request(
            'POST',
            "/api/task/edit/{$task->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Renamed',
                'icon' => 'cat',
                'hue' => null,
                'duration' => null,
                'stars' => 2,
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testRegularMemberCannotEditTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $task = $this->createTask($household);

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            "/api/task/edit/{$task->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Renamed',
                'icon' => 'cat',
                'hue' => null,
                'duration' => null,
                'stars' => 2,
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotEditTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $task = $this->createTask($household);

        $this->client->loginUser($outsider);
        $this->client->request(
            'POST',
            "/api/task/edit/{$task->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Renamed',
                'icon' => 'cat',
                'hue' => null,
                'duration' => null,
                'stars' => 2,
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- delete task (MANAGE_TASKS) ---

    public function testModeratorCanDeleteTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);
        $task = $this->createTask($household);
        $taskId = $task->getId();

        $this->client->loginUser($moderator);
        $this->client->request('DELETE', "/api/task/{$taskId}");

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->em->find(Task::class, $taskId));
    }

    public function testRegularMemberCannotDeleteTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $task = $this->createTask($household);

        $this->client->loginUser($member);
        $this->client->request('DELETE', "/api/task/{$task->getId()}");

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->em->find(Task::class, $task->getId()));
    }

    public function testNonMemberCannotDeleteTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $task = $this->createTask($household);

        $this->client->loginUser($outsider);
        $this->client->request('DELETE', "/api/task/{$task->getId()}");

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->em->find(Task::class, $task->getId()));
    }

    // --- mark task done (READ_HOUSEHOLD_CONTENTS for self; MANAGE_TASKS when completing as another user) ---

    public function testRegularMemberCanMarkTaskDoneForSelf(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $task = $this->createTask($household);

        $this->client->loginUser($member);
        $this->client->request('POST', "/api/task/mark-done/{$task->getId()}");

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotMarkTaskDone(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $task = $this->createTask($household);

        $this->client->loginUser($outsider);
        $this->client->request('POST', "/api/task/mark-done/{$task->getId()}");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testModeratorCanMarkTaskDoneAsAnotherUser(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);
        $target = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $task = $this->createTask($household);

        $this->client->loginUser($moderator);
        $this->client->request(
            'POST',
            "/api/task/mark-done/{$task->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['userId' => $target->getId()], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testRegularMemberCannotMarkTaskDoneAsAnotherUser(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $other = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $task = $this->createTask($household);

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            "/api/task/mark-done/{$task->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['userId' => $other->getId()], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- task log (READ_HOUSEHOLD_CONTENTS) ---

    public function testMemberCanReadTaskLog(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($member);
        $this->client->request('GET', "/api/task/log/{$household->getId()}");

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotReadTaskLog(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request('GET', "/api/task/log/{$household->getId()}");

        $this->assertResponseStatusCodeSame(403);
    }

    // --- task stats (READ_HOUSEHOLD_CONTENTS) ---

    public function testMemberCanReadTaskStats(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($member);
        $this->client->request('GET', "/api/task/stats/{$household->getId()}");

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotReadTaskStats(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request('GET', "/api/task/stats/{$household->getId()}");

        $this->assertResponseStatusCodeSame(403);
    }

    // --- task assign (READ_HOUSEHOLD_CONTENTS) ---

    public function testMemberCanAssignTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $task = $this->createTask($household);

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            "/api/task/assign/{$task->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['assignee' => $member->getId()], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotAssignTask(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $task = $this->createTask($household);

        $this->client->loginUser($outsider);
        $this->client->request(
            'POST',
            "/api/task/assign/{$task->getId()}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['assignee' => $outsider->getId()], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
    }
}
