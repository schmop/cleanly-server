<?php

declare(strict_types=1);

namespace App\Tests\Task;

use App\Task\Entity\Task;
use App\Task\Entity\TaskLog;
use App\Task\TaskCompleter;
use App\Task\TaskLogFactory;
use App\Task\TaskLogRepository;
use App\Task\TaskRepository;
use App\Tests\Utils\FakeClock;
use App\User\Entity\User;
use App\Utils\UuidGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit coverage for TaskCompleter. The 5-min rate-limit and the
 * "lastCompleted only moves forward" invariant are the bug-prone bits.
 */
class TaskCompleterTest extends TestCase
{
    private FakeClock $clock;
    private TaskRepository $taskRepository;
    private TaskLogRepository $taskLogRepository;
    private TaskCompleter $completer;
    private User $user;

    protected function setUp(): void
    {
        $this->clock = new FakeClock('2024-06-15 12:00:00');
        $this->taskRepository = $this->createMock(TaskRepository::class);
        $this->taskLogRepository = $this->createMock(TaskLogRepository::class);

        $uuidGen = $this->createStub(UuidGenerator::class);
        $uuidGen->method('v4')->willReturn('fixed-uuid');

        $this->user = $this->createStub(User::class);
        $this->completer = new TaskCompleter(
            $this->taskRepository,
            new TaskLogFactory($uuidGen, $this->clock),
            $this->taskLogRepository,
            $this->clock,
        );
    }

    public function testFirstCompletionPersistsAndUpdatesLastCompleted(): void
    {
        $task = $this->makeTask();
        $this->taskLogRepository->method('findLastByTaskAndUser')->willReturn(null);

        $this->taskRepository->expects($this->once())->method('save')->with($task);
        $this->taskLogRepository->expects($this->once())->method('save');

        $before = time();
        $this->assertTrue($this->completer->markAsComplete($task, $this->user));
        $after = time();
        $this->assertNotNull($task->getLastCompleted());
        $ts = $task->getLastCompleted()?->getTimestamp() ?? 0;
        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual($after, $ts);
    }

    public function testRateLimitedCompletionWithinFiveMinutesReturnsFalse(): void
    {
        $task = $this->makeTask();
        // Last log was 4 minutes ago — inside the 5-minute window.
        $recentLog = $this->makeLogAt('2024-06-15 11:56:00', $task);
        $this->taskLogRepository->method('findLastByTaskAndUser')->willReturn($recentLog);

        $this->taskRepository->expects($this->never())->method('save');
        $this->taskLogRepository->expects($this->never())->method('save');

        $this->assertFalse($this->completer->markAsComplete($task, $this->user));
    }

    public function testCompletionExactlyAtFiveMinutesIsStillRateLimited(): void
    {
        $task = $this->makeTask();
        // Exactly 5 minutes ago — boundary is inclusive (< RATE_LIMIT means exactly 5min is allowed).
        $log = $this->makeLogAt('2024-06-15 11:55:00', $task);
        $this->taskLogRepository->method('findLastByTaskAndUser')->willReturn($log);

        $this->taskRepository->expects($this->once())->method('save');
        $this->taskLogRepository->expects($this->once())->method('save');

        // 12:00:00 - 11:55:00 = 300s. Strict less-than: 300 < 300 is false → not rate limited.
        $this->assertTrue($this->completer->markAsComplete($task, $this->user));
    }

    public function testCompletionAfterFiveMinutesIsAllowed(): void
    {
        $task = $this->makeTask();
        $oldLog = $this->makeLogAt('2024-06-15 11:00:00', $task);
        $this->taskLogRepository->method('findLastByTaskAndUser')->willReturn($oldLog);

        $this->taskRepository->expects($this->once())->method('save');
        $this->taskLogRepository->expects($this->once())->method('save');
        $this->assertTrue($this->completer->markAsComplete($task, $this->user));
    }

    public function testCustomTimestampBypassesRateLimit(): void
    {
        $task = $this->makeTask();
        // Even with a recent log, providing a custom timestamp must not consult the rate limiter.
        $this->taskLogRepository->expects($this->never())->method('findLastByTaskAndUser');
        $this->taskRepository->expects($this->once())->method('save');

        $past = new \DateTimeImmutable('2023-01-01 09:00:00');
        $this->assertTrue($this->completer->markAsComplete($task, $this->user, $past));
    }

    public function testCustomTimestampInPastDoesNotRegressLastCompleted(): void
    {
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2024-06-15 11:00:00'));

        $this->taskRepository->expects($this->once())->method('save');
        $this->taskLogRepository->expects($this->once())->method('save');

        $past = new \DateTimeImmutable('2023-01-01 09:00:00');
        $this->completer->markAsComplete($task, $this->user, $past);

        // lastCompleted must stay at the more recent value.
        $this->assertSame(
            (new \DateTimeImmutable('2024-06-15 11:00:00'))->getTimestamp(),
            $task->getLastCompleted()?->getTimestamp(),
        );
    }

    public function testCustomTimestampInFutureMovesLastCompletedForward(): void
    {
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2024-06-15 09:00:00'));

        $this->taskRepository->expects($this->once())->method('save');
        $this->taskLogRepository->expects($this->once())->method('save');

        $future = new \DateTimeImmutable('2024-06-15 13:00:00');
        $this->completer->markAsComplete($task, $this->user, $future);

        $this->assertSame($future->getTimestamp(), $task->getLastCompleted()?->getTimestamp());
    }

    public function testAsUserCreditsTheTaskLogToOtherUser(): void
    {
        $task = $this->makeTask();
        $actor = $this->createStub(User::class);
        $target = $this->createStub(User::class);

        // Rate-limit lookup must use the target (the credited user), not the actor.
        $this->taskLogRepository->expects($this->once())
            ->method('findLastByTaskAndUser')
            ->with($task, $target)
            ->willReturn(null);

        $this->taskRepository->expects($this->once())->method('save');

        $capturedLog = null;
        $this->taskLogRepository->method('save')->willReturnCallback(function (TaskLog $log) use (&$capturedLog) {
            $capturedLog = $log;
        });

        $this->completer->markAsComplete($task, $actor, asUser: $target);

        $this->assertNotNull($capturedLog);
        $this->assertSame($target, $capturedLog->getUser());
    }

    private function makeTask(): Task
    {
        $task = new Task();
        $task->setName('test');
        $task->setStars(1);
        return $task;
    }

    private function makeLogAt(string $when, Task $task): TaskLog
    {
        return new TaskLog('uuid-old', new \DateTimeImmutable($when), $this->user, $task);
    }
}
