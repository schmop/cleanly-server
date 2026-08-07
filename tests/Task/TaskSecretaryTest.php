<?php

declare(strict_types=1);

namespace App\Tests\Task;

use App\Task\Entity\ReminderConfig;
use App\Task\Entity\Task;
use App\Task\TaskRepository;
use App\Task\TaskSecretary;
use App\Tests\Utils\FakeClock;
use PHPUnit\Framework\TestCase;

class TaskSecretaryTest extends TestCase
{
    // daysOfWeek bitmask: bit position = days from Monday.
    private const int MON = 1 << 0;
    private const int TUE = 1 << 1;
    private const int WED = 1 << 2;
    private const int THU = 1 << 3;
    private const int FRI = 1 << 4;
    private const int SAT = 1 << 5;
    private const int SUN = 1 << 6;

    public function testNoReminderConfigReturnsNull(): void
    {
        $secretary = $this->makeSecretary('2026-04-18 08:00:00');
        $task = $this->makeTask();

        $this->assertNull($secretary->computeNextReminderAt($task));
        $this->assertFalse($secretary->isReminderDue($task));
    }

    // --- Daily ------------------------------------------------------------

    public function testDailyFirstReminderIsTodayWhenTimeStillAhead(): void
    {
        $secretary = $this->makeSecretary('2026-04-18 08:00:00');
        $task = $this->makeTask();
        $task->setReminderConfig(ReminderConfig::daily(1, '09:00'));

        $this->assertNextReminder($secretary, $task, '2026-04-18 09:00:00');
    }

    public function testDailyFirstReminderRollsToTomorrowWhenTimeAlreadyPassed(): void
    {
        $secretary = $this->makeSecretary('2026-04-18 10:00:00');
        $task = $this->makeTask();
        $task->setReminderConfig(ReminderConfig::daily(1, '09:00'));

        $this->assertNextReminder($secretary, $task, '2026-04-19 09:00:00');
    }

    public function testDailyReminderRemindsDaily(): void
    {
        $secretary = $this->makeSecretary('2026-04-18 10:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-04-18 10:00:00'));
        $task->setReminderConfig(ReminderConfig::daily(1, '09:00'));

        $this->assertNextReminder($secretary, $task, '2026-04-19 09:00:00');
    }

    public function testDailyAfterLastReminderAdvancesByInterval(): void
    {
        $secretary = $this->makeSecretary('2026-04-21 10:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-04-18 10:00:00'));
        $task->setLastPushedAt(new \DateTimeImmutable('2026-04-21 09:00:00'));
        $task->setReminderConfig(ReminderConfig::daily(3, '09:00'));

        // First reminder after completion is 2026-04-19 09:00; interval 3 days
        // from that, the next after 2026-04-21 09:00 is 2026-04-22 09:00.
        $this->assertNextReminder($secretary, $task, '2026-04-22 09:00:00');
    }

    // --- Weekly -----------------------------------------------------------

    public function testWeeklySingleDayFiresOnFollowingOccurrence(): void
    {
        // 2026-04-18 is a Saturday.
        $secretary = $this->makeSecretary('2026-04-18 10:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-04-18 10:00:00'));
        // Bit 0 = Monday
        $task->setReminderConfig(ReminderConfig::weekly(1, self::MON, '09:00'));

        $this->assertNextReminder($secretary, $task, '2026-04-20 09:00:00');
    }

    public function testWeeklyMultipleDaysPicksNextDayInSameWeek(): void
    {
        // Completed Saturday 2026-04-18, reminder Sunday+Wednesday at 09:00.
        $secretary = $this->makeSecretary('2026-04-18 10:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-04-18 10:00:00'));
        $task->setReminderConfig(ReminderConfig::weekly(1, self::SUN | self::WED, '09:00'));

        // Next Sunday is 2026-04-19.
        $this->assertNextReminder($secretary, $task, '2026-04-19 09:00:00');
    }

    public function testWeeklyIntervalTwoSkipsTheInterveningWeek(): void
    {
        // Completed Mon 2026-04-13 10:00; reminder every 2 weeks on Monday 09:00.
        $secretary = $this->makeSecretary('2026-04-14 00:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-04-13 10:00:00'));
        $task->setReminderConfig(ReminderConfig::weekly(2, self::MON, '09:00'));

        // The Monday of the same 2-week cycle (2026-04-13) is already past,
        // so the next Monday in the schedule is 2 weeks later: 2026-04-27.
        $this->assertNextReminder($secretary, $task, '2026-04-27 09:00:00');
    }

    // --- Monthly: fixed day ----------------------------------------------

    public function testMonthlyDayReturnsSameMonthWhenFuture(): void
    {
        $secretary = $this->makeSecretary('2026-04-05 08:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-04-05 08:00:00'));
        $task->setReminderConfig(ReminderConfig::monthlyDay(1, 20, '09:00'));

        $this->assertNextReminder($secretary, $task, '2026-04-20 09:00:00');
    }

    public function testMonthlyDayClampsToLastDayOfShortMonth(): void
    {
        // Completed Jan 31, already reminded Jan 31 09:00 → next should clamp
        // Feb 31 → Feb 28, 2026 (non-leap year).
        $secretary = $this->makeSecretary('2026-01-31 10:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-01-15 10:00:00'));
        $task->setLastPushedAt(new \DateTimeImmutable('2026-01-31 09:00:00'));
        $task->setReminderConfig(ReminderConfig::monthlyDay(1, 31, '09:00'));

        $this->assertNextReminder($secretary, $task, '2026-02-28 09:00:00');
    }

    // --- Monthly: nth weekday --------------------------------------------

    public function testMonthlyFirstMondayFromMidMonthRollsToNextMonth(): void
    {
        // 2026-04-01 is Wednesday → first Monday of April = 2026-04-06.
        // Completed 2026-04-18 so first Monday already passed → advance one
        // month. 2026-05-01 is Friday → first Monday of May = 2026-05-04.
        $secretary = $this->makeSecretary('2026-04-18 10:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-04-18 10:00:00'));
        // weekOccurrence=1, weekDay=1 (Monday)
        $task->setReminderConfig(ReminderConfig::monthlyWeekday(1, 1, 1, '09:00'));

        $this->assertNextReminder($secretary, $task, '2026-05-04 09:00:00');
    }

    public function testMonthlyLastFridayReturnsLastFridayOfMonth(): void
    {
        // 2026-04-30 is Thursday → last Friday of April = 2026-04-24.
        $secretary = $this->makeSecretary('2026-04-18 10:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-04-18 10:00:00'));
        // weekOccurrence=-1 (last), weekDay=5 (Friday)
        $task->setReminderConfig(ReminderConfig::monthlyWeekday(1, -1, 5, '09:00'));

        $this->assertNextReminder($secretary, $task, '2026-04-24 09:00:00');
    }

    public function testMonthlyFifthSundayFallsBackToLastWhenMissing(): void
    {
        // April 2026 has only 4 Sundays (5, 12, 19, 26) → 5th Sunday should
        // fall back to the last Sunday, 2026-04-26.
        $secretary = $this->makeSecretary('2026-04-01 00:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-04-01 00:00:00'));
        // weekOccurrence=5, weekDay=0 (Sunday)
        $task->setReminderConfig(ReminderConfig::monthlyWeekday(1, 5, 0, '09:00'));

        $this->assertNextReminder($secretary, $task, '2026-04-26 09:00:00');
    }

    // --- Yearly -----------------------------------------------------------

    public function testYearlyReturnsThisYearWhenDateFuture(): void
    {
        $secretary = $this->makeSecretary('2026-04-18 10:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-01-10 10:00:00'));
        $task->setReminderConfig(ReminderConfig::yearly(1, 6, 1, '09:00'));

        $this->assertNextReminder($secretary, $task, '2026-06-01 09:00:00');
    }

    public function testYearlyClampsFeb29InNonLeapYear(): void
    {
        $secretary = $this->makeSecretary('2026-01-10 10:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-01-05 10:00:00'));
        $task->setReminderConfig(ReminderConfig::yearly(1, 2, 29, '09:00'));

        // 2026 is not a leap year → Feb 29 clamps to Feb 28.
        $this->assertNextReminder($secretary, $task, '2026-02-28 09:00:00');
    }

    // --- Due-detection & marking -----------------------------------------

    public function testIsReminderDueTrueWhenNextIsNow(): void
    {
        $secretary = $this->makeSecretary('2026-04-18 09:00:00');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-04-17 10:00:00'));
        $task->setReminderConfig(ReminderConfig::daily(1, '09:00'));

        // Next reminder is 2026-04-18 09:00, which equals "now".
        $this->assertTrue($secretary->isReminderDue($task));
    }

    public function testIsReminderDueFalseBeforeTime(): void
    {
        $secretary = $this->makeSecretary('2026-04-18 08:59:59');
        $task = $this->makeTask();
        $task->setLastCompleted(new \DateTimeImmutable('2026-04-17 10:00:00'));
        $task->setReminderConfig(ReminderConfig::daily(1, '09:00'));

        $this->assertFalse($secretary->isReminderDue($task));
    }

    public function testMarkTaskAsPushedSetsLastPushedAtAndSaves(): void
    {
        $now = new \DateTimeImmutable('2026-04-18 09:00:00');
        $clock = new FakeClock('2026-04-18 09:00:00');
        $repo = $this->createMock(TaskRepository::class);
        $task = $this->makeTask();

        $repo->expects($this->once())
            ->method('save')
            ->with($this->identicalTo($task));

        $secretary = new TaskSecretary($clock, $repo);
        $secretary->markTaskAsPushed($task);

        $this->assertNotNull($task->getLastPushedAt());
        $this->assertEquals($now->getTimestamp(), $task->getLastPushedAt()->getTimestamp());
    }

    // --- Helpers ----------------------------------------------------------

    private function makeSecretary(string $now): TaskSecretary
    {
        return new TaskSecretary(
            new FakeClock($now),
            $this->createStub(TaskRepository::class),
        );
    }

    private function makeTask(): Task
    {
        $task = new Task();
        $task->setName('Test Task');

        return $task;
    }

    private function assertNextReminder(
        TaskSecretary $secretary,
        Task $task,
        string $expected,
    ): void {
        $next = $secretary->computeNextReminderAt($task);
        $this->assertNotNull($next, 'Expected a next reminder time');
        $this->assertSame($expected, $next->format('Y-m-d H:i:s'));
    }
}
