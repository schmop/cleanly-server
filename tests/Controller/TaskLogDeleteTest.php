<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdPrivilege;
use App\Household\ReassignmentStrategy;
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

    private string $runId = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->runId = substr(md5(uniqid()), 0, 8);
        $this->households = [];
        $this->users = [];
    }

    protected function tearDown(): void
    {
        try {
            // Deleting households cascades to tasks and task logs.
            // Re-fetch entities via a fresh EM in case the current one was closed by a failed flush.
            $em = static::getContainer()->get(EntityManagerInterface::class);
            foreach ($this->households as $household) {
                $h = $em->find(Household::class, $household->getId());
                if ($h !== null) {
                    $em->remove($h);
                }
            }
            foreach ($this->users as $user) {
                $u = $em->find(User::class, $user->getId());
                if ($u !== null) {
                    $em->remove($u);
                }
            }
            $em->flush();
        } finally {
            parent::tearDown();
        }
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
        $outsider = $this->createUser("outsider_{$this->runId}@test.example");

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
        [$owner, $household, $task] = $this->createBaseFixture();
        $log = $this->createTaskLog($task, $owner, new \DateTimeImmutable());
        $task->setLastCompleted($log->getTimestamp());
        $this->em->flush();

        $this->client->loginUser($owner);
        $this->client->request('DELETE', "/api/task/log/{$log->getUuid()}");

        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $updatedTask = $this->em->find(Task::class, $task->getId());
        $this->assertNull($updatedTask->getLastCompleted());
    }

    public function testLastCompletedRevertsToPreviousLogAfterDeletingNewest(): void
    {
        [$owner, $household, $task] = $this->createBaseFixture();

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
        [$owner, $household, $task] = $this->createBaseFixture();

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
        [$user, $household, $task] = $this->createBaseFixture();
        $log = $this->createTaskLog($task, $user, new \DateTimeImmutable());

        $task->setLastCompleted($log->getTimestamp());
        $this->em->flush();

        return [$user, $household, $task, $log];
    }

    /**
     * @return array{User, Household, Task}
     */
    private function createBaseFixture(): array
    {
        $user = $this->createUser("owner_{$this->runId}@test.example");
        $household = $this->createHousehold($user);
        $task = $this->createTask($household);

        return [$user, $household, $task];
    }

    private function createUser(string $email): User
    {
        $user = new User($email, 'Test User');
        $user->setPassword('irrelevant'); // loginUser() bypasses password checks
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
        $household->setReassignmentStrategy(ReassignmentStrategy::None);
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
        $task->setLastNotifiedAt(new \DateTimeImmutable());
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
