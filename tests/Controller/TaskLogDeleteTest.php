<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdPrivilege;
use App\Task\Entity\Task;
use App\Task\Entity\TaskLog;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TaskLogDeleteTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    /** @var Household[] */
    private array $households = [];

    /** @var User[] */
    private array $users = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        // Deleting households cascades to tasks and task logs
        foreach ($this->households as $household) {
            $household = $this->em->find(Household::class, $household->getId());
            if ($household !== null) {
                $this->em->remove($household);
            }
        }
        foreach ($this->users as $user) {
            $user = $this->em->find(User::class, $user->getId());
            if ($user !== null) {
                $this->em->remove($user);
            }
        }
        $this->em->flush();

        parent::tearDown();
    }

    // --- Success cases ---

    public function testOwnerCanDeleteOwnRecentLog(): void
    {
        [$user, $household, $task, $log] = $this->createFixture();

        $this->client->loginUser($user);
        $this->client->request('DELETE', "/api/task/log/{$log->getUuid()}");

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->em->find(TaskLog::class, $log->getUuid()));
    }

    public function testModeratorCanDeleteAnotherUsersLog(): void
    {
        [$owner, $household, $task, $log] = $this->createFixture();
        $moderator = $this->createUserInHousehold($household, HouseholdPrivilege::PRIVILEGE_MODERATOR);

        $this->client->loginUser($moderator);
        $this->client->request('DELETE', "/api/task/log/{$log->getUuid()}");

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->em->find(TaskLog::class, $log->getUuid()));
    }

    public function testAdminCanDeleteAnotherUsersLog(): void
    {
        [$owner, $household, $task, $log] = $this->createFixture();
        $admin = $this->createUserInHousehold($household, HouseholdPrivilege::PRIVILEGE_ADMIN);

        $this->client->loginUser($admin);
        $this->client->request('DELETE', "/api/task/log/{$log->getUuid()}");

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->em->find(TaskLog::class, $log->getUuid()));
    }

    // --- Authorization failures ---

    public function testRegularMemberCannotDeleteAnotherUsersLog(): void
    {
        [$owner, $household, $task, $log] = $this->createFixture();
        $otherUser = $this->createUserInHousehold($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($otherUser);
        $this->client->request('DELETE', "/api/task/log/{$log->getUuid()}");

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->em->find(TaskLog::class, $log->getUuid()));
    }

    public function testNonMemberCannotDeleteLog(): void
    {
        [$owner, $household, $task, $log] = $this->createFixture();
        $outsider = $this->createUser('outsider@test.example');

        $this->client->loginUser($outsider);
        $this->client->request('DELETE', "/api/task/log/{$log->getUuid()}");

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->em->find(TaskLog::class, $log->getUuid()));
    }

    public function testOldLogCannotBeDeleted(): void
    {
        [$owner, $household, $task] = $this->createFixture();
        $oldLog = $this->createTaskLog($task, $owner, new \DateTimeImmutable('-25 hours'));

        $this->client->loginUser($owner);
        $this->client->request('DELETE', "/api/task/log/{$oldLog->getUuid()}");

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->em->find(TaskLog::class, $oldLog->getUuid()));
    }

    // --- lastCompleted updates ---

    public function testLastCompletedBecomesNullAfterDeletingOnlyLog(): void
    {
        [$owner, $household, $task, $log] = $this->createFixture();

        $this->client->loginUser($owner);
        $this->client->request('DELETE', "/api/task/log/{$log->getUuid()}");

        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $updatedTask = $this->em->find(Task::class, $task->getId());
        $this->assertNull($updatedTask->getLastCompleted());
    }

    public function testLastCompletedRevertsToPreviousLogAfterDeletingNewest(): void
    {
        [$owner, $household, $task] = $this->createFixture();

        $olderTime = new \DateTimeImmutable('-2 hours');
        $newerTime = new \DateTimeImmutable('-1 hour');

        $olderLog = $this->createTaskLog($task, $owner, $olderTime);
        $newerLog = $this->createTaskLog($task, $owner, $newerTime);

        $task->setLastCompleted($newerTime);
        $this->em->flush();

        $this->client->loginUser($owner);
        $this->client->request('DELETE', "/api/task/log/{$newerLog->getUuid()}");

        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $updatedTask = $this->em->find(Task::class, $task->getId());
        $this->assertNotNull($updatedTask->getLastCompleted());
        $this->assertEquals($olderTime->getTimestamp(), $updatedTask->getLastCompleted()->getTimestamp());
    }

    public function testDeletingOlderLogDoesNotAffectLastCompleted(): void
    {
        [$owner, $household, $task] = $this->createFixture();

        $olderTime = new \DateTimeImmutable('-2 hours');
        $newerTime = new \DateTimeImmutable('-1 hour');

        $olderLog = $this->createTaskLog($task, $owner, $olderTime);
        $this->createTaskLog($task, $owner, $newerTime);

        $task->setLastCompleted($newerTime);
        $this->em->flush();

        $this->client->loginUser($owner);
        $this->client->request('DELETE', "/api/task/log/{$olderLog->getUuid()}");

        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $updatedTask = $this->em->find(Task::class, $task->getId());
        $this->assertEquals($newerTime->getTimestamp(), $updatedTask->getLastCompleted()->getTimestamp());
    }

    // --- Fixtures ---

    /**
     * @return array{User, Household, Task, TaskLog}
     */
    private function createFixture(): array
    {
        $user = $this->createUser('owner@test.example');
        $household = $this->createHousehold($user);
        $task = $this->createTask($household);
        $log = $this->createTaskLog($task, $user, new \DateTimeImmutable());

        $task->setLastCompleted($log->getTimestamp());
        $this->em->flush();

        return [$user, $household, $task, $log];
    }

    private function createUser(string $email): User
    {
        $user = new User($email, 'Test User');
        $this->em->persist($user);
        $this->em->flush();
        $this->users[] = $user;

        return $user;
    }

    private function createUserInHousehold(Household $household, int $privilege): User
    {
        $user = $this->createUser(uniqid('user_') . '@test.example');
        $household->addMember($user);
        $household->setUserPrivilege($user, $privilege);
        $this->em->flush();

        return $user;
    }

    private function createHousehold(User $owner): Household
    {
        $household = new Household();
        $household->setName('Test Household');
        $household->addMember($owner);
        $household->setUserPrivilege($owner, HouseholdPrivilege::PRIVILEGE_USER);
        $this->em->persist($household);
        $this->em->flush();
        $this->households[] = $household;

        return $household;
    }

    private function createTask(Household $household): Task
    {
        $task = new Task();
        $task->setHousehold($household);
        $task->setName('Test Task');
        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }

    private function createTaskLog(Task $task, User $user, \DateTimeImmutable $at): TaskLog
    {
        $log = new TaskLog(uniqid('log_'), $at, $user, $task);
        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }
}
