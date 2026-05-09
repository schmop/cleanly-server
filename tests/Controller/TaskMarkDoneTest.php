<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Household\Entity\HouseholdPrivilege;
use App\Task\Entity\Task;
use App\Task\Entity\TaskLog;
use App\Tests\Controller\Privilege\PrivilegeFixtureTrait;
use App\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Behaviour of `POST /api/task/mark-done/{id}` when the optional `userId`
 * (mark on behalf of another member) and `timestamp` (record at a past time)
 * fields are combined. Privilege gating for the userId path is covered in
 * `Privilege/TaskPrivilegeTest`; this file focuses on persistence side-effects.
 */
class TaskMarkDoneTest extends WebTestCase
{
    use PrivilegeFixtureTrait;

    private KernelBrowser $client;
    private JWTTokenManagerInterface $jwtManager;
    private string $authHeader = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->initFixtures();
        $this->jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupFixtures();
        } finally {
            parent::tearDown();
        }
    }

    public function testModeratorMarksTaskDoneAsAnotherMemberAtCustomTimestamp(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $moderator = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_MODERATOR, 'mod');
        $target = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER, 'target');
        $task = $this->createTask($household);

        $past = (new \DateTimeImmutable('2024-06-15 10:30:00'))->getTimestamp();

        $this->authenticateAs($moderator);
        $this->client->request(
            'POST',
            "/api/task/mark-done/{$task->getId()}",
            server: $this->authServer('application/json'),
            content: json_encode(
                ['userId' => $target->getId(), 'timestamp' => $past],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->assertResponseIsSuccessful();

        // The created log is credited to the target, not the moderator, and
        // carries the custom timestamp verbatim.
        $log = $this->em->getRepository(TaskLog::class)->findOneBy(['task' => $task->getId()]);
        $this->assertNotNull($log);
        $this->assertSame($target->getId(), $log->getUser()->getId());
        $this->assertSame($past, $log->getTimestamp()->getTimestamp());
    }

    public function testCustomTimestampInThePastDoesNotMoveLastCompletedBackwards(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $target = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER, 'target');
        $task = $this->createTask($household);

        // First mark the task done now (admin → self).
        $this->authenticateAs($admin);
        $this->client->request(
            'POST',
            "/api/task/mark-done/{$task->getId()}",
            server: $this->authServer('application/json'),
            content: '',
        );
        $this->assertResponseIsSuccessful();
        // The KernelBrowser rebuilds the kernel per request, so the entity
        // we created earlier is detached. Re-fetch by id from a fresh EM.
        $taskId = $task->getId();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $taskAfterFirst = $em->find(Task::class, $taskId);
        $this->assertNotNull($taskAfterFirst);
        $lastCompletedAfterFirst = $taskAfterFirst->getLastCompleted();
        $this->assertNotNull($lastCompletedAfterFirst);

        // Now retroactively log a completion for `target` from a year ago.
        $oneYearAgo = (new \DateTimeImmutable('-1 year'))->getTimestamp();
        $this->client->request(
            'POST',
            "/api/task/mark-done/{$taskId}",
            server: $this->authServer('application/json'),
            content: json_encode(
                ['userId' => $target->getId(), 'timestamp' => $oneYearAgo],
                JSON_THROW_ON_ERROR,
            ),
        );
        $this->assertResponseIsSuccessful();

        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $taskAfterSecond = $em->find(Task::class, $taskId);
        $this->assertNotNull($taskAfterSecond);
        // Older custom timestamp must not overwrite a more recent lastCompleted.
        $this->assertSame(
            $lastCompletedAfterFirst->getTimestamp(),
            $taskAfterSecond->getLastCompleted()?->getTimestamp(),
        );
    }

    public function testCustomTimestampInTheFutureBypassesRateLimit(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $target = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER, 'target');
        $task = $this->createTask($household);

        $this->authenticateAs($admin);

        // Two retroactive completions for the same target within the 5-min
        // rate-limit window. The "custom timestamp" path is documented as
        // skipping the rate limit, so both must succeed.
        $first = (new \DateTimeImmutable('2024-06-15 09:00:00'))->getTimestamp();
        $second = $first + 60;

        foreach ([$first, $second] as $timestamp) {
            $this->client->request(
                'POST',
                "/api/task/mark-done/{$task->getId()}",
                server: $this->authServer('application/json'),
                content: json_encode(
                    ['userId' => $target->getId(), 'timestamp' => $timestamp],
                    JSON_THROW_ON_ERROR,
                ),
            );
            $this->assertResponseIsSuccessful();
        }

        $logs = $this->em->getRepository(TaskLog::class)->findBy(['task' => $task->getId()]);
        $this->assertCount(2, $logs);
    }

    public function testReturns400WhenAsUserIsNotAHouseholdMember(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $task = $this->createTask($household);

        $this->authenticateAs($admin);
        $this->client->request(
            'POST',
            "/api/task/mark-done/{$task->getId()}",
            server: $this->authServer('application/json'),
            content: json_encode(
                ['userId' => $outsider->getId(), 'timestamp' => time() - 3600],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame(
            0,
            count($this->em->getRepository(TaskLog::class)->findBy(['task' => $task->getId()])),
        );
    }

    private function authenticateAs(User $user): void
    {
        $this->authHeader = 'Bearer ' . $this->jwtManager->create($user);
    }

    /** @return array<string, string> */
    private function authServer(string $contentType = ''): array
    {
        $headers = ['HTTP_AUTHORIZATION' => $this->authHeader];
        if ($contentType !== '') {
            $headers['CONTENT_TYPE'] = $contentType;
        }
        return $headers;
    }
}
