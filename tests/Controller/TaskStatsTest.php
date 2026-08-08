<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Household\Entity\HouseholdPrivilege;
use App\Tests\Controller\Privilege\PrivilegeFixtureTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Payload of `GET /api/task/stats/{id}`. Privilege gating lives in
 * `Privilege/TaskPrivilegeTest`; this file pins the numbers the endpoint
 * reports, which the privilege tests never look at because they run against
 * households with no completion logs at all.
 */
class TaskStatsTest extends WebTestCase
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

    public function testDurationStatsAreComputedFromGapsBetweenCompletions(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $task = $this->createTask($household, 'Recurring chore');

        // Completions one hour, then two hours apart -> deltas [3600, 7200].
        $base = new \DateTimeImmutable('2024-06-15 08:00:00');
        $this->createTaskLog($task, $admin, $base);
        $this->createTaskLog($task, $admin, $base->modify('+1 hour'));
        $this->createTaskLog($task, $admin, $base->modify('+3 hours'));

        $this->client->loginUser($admin);
        $this->client->request('GET', "/api/task/stats/{$household->getId()}");
        $this->assertResponseIsSuccessful();

        $stats = $this->statsFor($task->getId());
        $this->assertSame(3600, $stats['min']);
        $this->assertSame(7200, $stats['max']);
        $this->assertSame(2, $stats['num']);
        $this->assertEqualsWithDelta(5400.0, (float)$stats['average'], 0.001);
    }

    public function testSingleCompletionYieldsNullDurationStats(): void
    {
        // One completion means zero gaps, so Statistics::{average,min,max} all
        // run their empty-array branch. A division-by-zero here would 500.
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $task = $this->createTask($household, 'Done once');

        $this->createTaskLog($task, $admin, new \DateTimeImmutable('2024-06-15 08:00:00'));

        $this->client->loginUser($admin);
        $this->client->request('GET', "/api/task/stats/{$household->getId()}");
        $this->assertResponseIsSuccessful();

        $stats = $this->statsFor($task->getId());
        $this->assertNull($stats['average']);
        $this->assertNull($stats['min']);
        $this->assertNull($stats['max']);
        $this->assertSame(0, $stats['num']);
    }

    public function testUserParticipationsCountCompletionsPerMember(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $task = $this->createTask($household, 'Shared chore');

        $base = new \DateTimeImmutable('2024-06-15 08:00:00');
        $this->createTaskLog($task, $admin, $base);
        $this->createTaskLog($task, $member, $base->modify('+1 hour'));
        $this->createTaskLog($task, $member, $base->modify('+2 hours'));

        $this->client->loginUser($admin);
        $this->client->request('GET', "/api/task/stats/{$household->getId()}");
        $this->assertResponseIsSuccessful();

        $taskId = $task->getId();
        self::assertNotNull($taskId);
        $participations = $this->payload()['userParticipations'];
        self::assertIsArray($participations);
        $perUser = $participations[$taskId] ?? null;
        self::assertIsArray($perUser);
        $this->assertSame(1, $perUser[$admin->getId()] ?? null);
        $this->assertSame(2, $perUser[$member->getId()] ?? null);
    }

    public function testHouseholdWithoutCompletionsReportsEmptyStats(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $this->createTask($household);

        $this->client->loginUser($admin);
        $this->client->request('GET', "/api/task/stats/{$household->getId()}");
        $this->assertResponseIsSuccessful();

        $payload = $this->payload();
        $this->assertSame([], $payload['durations']);
        $this->assertSame([], $payload['userParticipations']);
    }

    /**
     * @return array{average: float|int|null, min: int|null, max: int|null, num: int}
     */
    private function statsFor(?int $taskId): array
    {
        self::assertNotNull($taskId);
        $durations = $this->payload()['durations'];
        self::assertIsArray($durations);
        self::assertArrayHasKey($taskId, $durations);
        $stats = $durations[$taskId];
        self::assertIsArray($stats);
        foreach (['average', 'min', 'max', 'num'] as $key) {
            self::assertArrayHasKey($key, $stats);
        }

        /** @var array{average: float|int|null, min: int|null, max: int|null, num: int} $stats */
        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $decoded = json_decode(
            (string)$this->client->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
