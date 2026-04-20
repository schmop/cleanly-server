<?php

namespace App\Task;

use App\Persistence\PersistenceException;
use App\Task\Entity\ReminderConfig;
use App\Task\Entity\Task;
use App\Task\Exception\ReminderComputationException;
use App\Utils\Clock;

readonly class TaskSecretary
{
    public function __construct(
        private Clock          $clock,
        private TaskRepository $taskRepository,
    ) {
    }

    /**
     * @throws ReminderComputationException
     */
    public function isTaskDue(Task $task): bool
    {
        return ReminderComputationException::wrap(function () use ($task): bool {
            $lastCompleted = $task->getLastCompleted();
            if (null === $lastCompleted || null === $task->getDuration()) {
                return false;
            }
            $dueDate = $lastCompleted->add(
                new \DateInterval(sprintf('PT%dH', $task->getDuration()))
            );

            return $dueDate < $this->clock->now();
        });
    }

    public function wasAlreadyNotified(Task $task): bool
    {
        $lastCompleted = $task->getLastCompleted();
        if (null === $lastCompleted) {
            return true;
        }
        $lastPushedAt = $task->getLastPushedAt();
        return $lastPushedAt !== null && $lastCompleted < $lastPushedAt;
    }

    /**
     * @throws ReminderComputationException
     */
    public function isReminderDue(Task $task): bool
    {
        $next = $this->computeNextReminderAt($task);
        if ($next === null) {
            return false;
        }

        return $next <= $this->clock->now();
    }

    /**
     * @throws PersistenceException
     */
    public function markTaskAsPushed(Task $task): void
    {
        $task->setLastPushedAt($this->clock->now());
        $this->taskRepository->save($task);
    }

    /**
     * @throws ReminderComputationException
     */
    public function computeNextReminderAt(Task $task): ?\DateTimeImmutable
    {
        return ReminderComputationException::wrap(function () use ($task): ?\DateTimeImmutable {
            $config = $task->getReminderConfig();
            if ($config === null) {
                return null;
            }

            [$hour, $minute] = array_map('intval', explode(':', $config->time));

            // If the task was never completed, use now as the anchor so the first
            // reminder fires at the next natural occurrence from now.
            $lastCompleted = $task->getLastCompleted() ?? $this->clock->now();
            $lastPushedAt = $task->getLastPushedAt() ?? new \DateTimeImmutable('@0');
            $afterDate = $lastCompleted > $lastPushedAt ? $lastCompleted : $lastPushedAt;

            return match ($config->type) {
                ReminderConfig::TYPE_DAILY => $this->computeNextDaily(
                    $lastCompleted, $afterDate, $config->interval, $hour, $minute
                ),
                ReminderConfig::TYPE_WEEKLY => $this->computeNextWeekly(
                    $lastCompleted, $afterDate, $config->interval, $config->daysOfWeek ?? 1, $hour, $minute
                ),
                ReminderConfig::TYPE_MONTHLY_DAY => $this->computeNextMonthlyDay(
                    $lastCompleted, $afterDate, $config->interval, $config->monthDay ?? 1, $hour, $minute
                ),
                ReminderConfig::TYPE_MONTHLY_WEEKDAY => $this->computeNextMonthlyWeekday(
                    $lastCompleted, $afterDate, $config->interval, $config->weekOccurrence ?? 1, $config->weekDay ?? 1, $hour, $minute
                ),
                ReminderConfig::TYPE_YEARLY => $this->computeNextYearly(
                    $lastCompleted, $afterDate, $config->interval, $config->month ?? 1, $config->day ?? 1, $hour, $minute
                ),
                default => null,
            };
        });
    }

    // -------------------------------------------------------------------------
    // Daily
    // -------------------------------------------------------------------------

    /**
     * @throws \DateMalformedIntervalStringException
     * @throws \DivisionByZeroError
     */
    private function computeNextDaily(
        \DateTimeImmutable $lastCompleted,
        \DateTimeImmutable $afterDate,
        int $intervalDays,
        int $hour,
        int $minute,
    ): \DateTimeImmutable {
        $anchor = $lastCompleted->setTime($hour, $minute, 0);
        if ($anchor <= $lastCompleted) {
            $anchor = $anchor->add(new \DateInterval('P1D'));
        }

        if ($anchor > $afterDate) {
            return $anchor;
        }

        $intervalSeconds = $intervalDays * 86400;
        $n = (int) floor(($afterDate->getTimestamp() - $anchor->getTimestamp()) / $intervalSeconds);

        return $anchor->add(new \DateInterval('P' . (($n + 1) * $intervalDays) . 'D'));
    }

    // -------------------------------------------------------------------------
    // Weekly
    // -------------------------------------------------------------------------

    /**
     * @param int $daysOfWeekMask Bitmask: bit 0=Mon, 1=Tue … 5=Sat, 6=Sun. Bit position = offset from Monday.
     * @throws \DateInvalidOperationException
     * @throws \DateMalformedIntervalStringException
     * @throws \DivisionByZeroError
     */
    private function computeNextWeekly(
        \DateTimeImmutable $lastCompleted,
        \DateTimeImmutable $afterDate,
        int $intervalWeeks,
        int $daysOfWeekMask,
        int $hour,
        int $minute,
    ): \DateTimeImmutable {
        if ($daysOfWeekMask <= 0) {
            $daysOfWeekMask = 1; // Monday
        }

        // Monday of the week containing lastCompleted
        $dow = (int) $lastCompleted->format('N'); // ISO: 1=Mon…7=Sun
        $anchorWeekStart = $lastCompleted->setTime(0, 0, 0)->sub(new \DateInterval('P' . ($dow - 1) . 'D'));

        $intervalSeconds = $intervalWeeks * 7 * 86400;

        // Which cycle does afterDate fall into?
        $dowAfter = (int) $afterDate->format('N');
        $weekStartOfAfterDate = $afterDate->setTime(0, 0, 0)->sub(new \DateInterval('P' . ($dowAfter - 1) . 'D'));

        $secsDiff = $weekStartOfAfterDate->getTimestamp() - $anchorWeekStart->getTimestamp();
        $cycleN = max(0, (int) floor($secsDiff / $intervalSeconds));

        // Try current cycle then the next one. Bits iterate in Mon→Sun order,
        // which is exactly the in-week chronological order we want.
        for ($attempt = 0; $attempt <= 1; $attempt++) {
            $cycleWeekStart = $anchorWeekStart->add(
                new \DateInterval('P' . (($cycleN + $attempt) * $intervalWeeks) . 'W')
            );
            for ($bit = 0; $bit <= 6; $bit++) {
                if (($daysOfWeekMask & (1 << $bit)) === 0) {
                    continue;
                }
                $candidate = $cycleWeekStart
                    ->add(new \DateInterval("P{$bit}D"))
                    ->setTime($hour, $minute, 0);
                if ($candidate > $afterDate) {
                    return $candidate;
                }
            }
        }

        // Fallback (should not normally be reached): first set bit in the next cycle.
        $firstBit = 0;
        for ($bit = 0; $bit <= 6; $bit++) {
            if (($daysOfWeekMask & (1 << $bit)) !== 0) {
                $firstBit = $bit;
                break;
            }
        }
        $nextCycleWeekStart = $anchorWeekStart->add(
            new \DateInterval('P' . (($cycleN + 2) * $intervalWeeks) . 'W')
        );

        return $nextCycleWeekStart->add(new \DateInterval("P{$firstBit}D"))->setTime($hour, $minute, 0);
    }

    // -------------------------------------------------------------------------
    // Monthly – fixed day
    // -------------------------------------------------------------------------

    /**
     * @throws \DivisionByZeroError
     * @throws \DateMalformedStringException
     */
    private function computeNextMonthlyDay(
        \DateTimeImmutable $lastCompleted,
        \DateTimeImmutable $afterDate,
        int $intervalMonths,
        int $monthDay,
        int $hour,
        int $minute,
    ): \DateTimeImmutable {
        $y = (int) $lastCompleted->format('Y');
        $m = (int) $lastCompleted->format('n');

        $candidate = $this->monthDayOccurrence($y, $m, $monthDay, $hour, $minute);
        if ($candidate <= $lastCompleted) {
            [$y, $m] = $this->addMonths($y, $m, $intervalMonths);
            $candidate = $this->monthDayOccurrence($y, $m, $monthDay, $hour, $minute);
        }

        if ($candidate > $afterDate) {
            return $candidate;
        }

        // Fast-forward to the vicinity of afterDate
        $monthsDiff = ((int) $afterDate->format('Y')) * 12 + ((int) $afterDate->format('n'))
            - ((int) $candidate->format('Y')) * 12 - ((int) $candidate->format('n'));
        $skip = max(0, (int) floor($monthsDiff / $intervalMonths) - 1);
        if ($skip > 0) {
            [$cy, $cm] = $this->addMonths((int) $candidate->format('Y'), (int) $candidate->format('n'), $skip * $intervalMonths);
            $candidate = $this->monthDayOccurrence($cy, $cm, $monthDay, $hour, $minute);
        }

        while ($candidate <= $afterDate) {
            [$ny, $nm] = $this->addMonths((int) $candidate->format('Y'), (int) $candidate->format('n'), $intervalMonths);
            $candidate = $this->monthDayOccurrence($ny, $nm, $monthDay, $hour, $minute);
        }

        return $candidate;
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function monthDayOccurrence(int $year, int $month, int $monthDay, int $hour, int $minute): \DateTimeImmutable
    {
        $maxDay = (int) (new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month)))->format('t');

        return new \DateTimeImmutable(
            sprintf('%d-%02d-%02d %02d:%02d:00', $year, $month, min($monthDay, $maxDay), $hour, $minute)
        );
    }

    // -------------------------------------------------------------------------
    // Monthly – nth weekday of month
    // -------------------------------------------------------------------------

    /**
     * @throws \DivisionByZeroError
     * @throws \DateInvalidOperationException
     * @throws \DateMalformedIntervalStringException
     * @throws \DateMalformedStringException
     */
    private function computeNextMonthlyWeekday(
        \DateTimeImmutable $lastCompleted,
        \DateTimeImmutable $afterDate,
        int $intervalMonths,
        int $weekOccurrence,
        int $weekDay,
        int $hour,
        int $minute,
    ): \DateTimeImmutable {
        $y = (int) $lastCompleted->format('Y');
        $m = (int) $lastCompleted->format('n');

        $candidate = $this->monthWeekdayOccurrence($y, $m, $weekOccurrence, $weekDay, $hour, $minute);
        if ($candidate <= $lastCompleted) {
            [$y, $m] = $this->addMonths($y, $m, $intervalMonths);
            $candidate = $this->monthWeekdayOccurrence($y, $m, $weekOccurrence, $weekDay, $hour, $minute);
        }

        if ($candidate > $afterDate) {
            return $candidate;
        }

        $monthsDiff = ((int) $afterDate->format('Y')) * 12 + ((int) $afterDate->format('n'))
            - ((int) $candidate->format('Y')) * 12 - ((int) $candidate->format('n'));
        $skip = max(0, (int) floor($monthsDiff / $intervalMonths) - 1);
        if ($skip > 0) {
            [$cy, $cm] = $this->addMonths((int) $candidate->format('Y'), (int) $candidate->format('n'), $skip * $intervalMonths);
            $candidate = $this->monthWeekdayOccurrence($cy, $cm, $weekOccurrence, $weekDay, $hour, $minute);
        }

        while ($candidate <= $afterDate) {
            [$ny, $nm] = $this->addMonths((int) $candidate->format('Y'), (int) $candidate->format('n'), $intervalMonths);
            $candidate = $this->monthWeekdayOccurrence($ny, $nm, $weekOccurrence, $weekDay, $hour, $minute);
        }

        return $candidate;
    }

    /**
     * @param int $weekOccurrence  negative = nth-from-last (-1=last, -2=2nd-last, …), positive = nth-from-start (1–4)
     *
     * @throws \DateInvalidOperationException
     * @throws \DateMalformedIntervalStringException
     * @throws \DateMalformedStringException
     */
    private function monthWeekdayOccurrence(int $year, int $month, int $weekOccurrence, int $weekDay, int $hour, int $minute): \DateTimeImmutable
    {
        if ($weekOccurrence < 0) {
            $maxDay = (int) (new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month)))->format('t');
            $lastDay = new \DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, $maxDay));
            $currentDow = (int) $lastDay->format('w'); // 0=Sun … 6=Sat
            $daysBack = ($currentDow - $weekDay + 7) % 7;
            $lastOccurrence = $lastDay->sub(new \DateInterval("P{$daysBack}D"));
            $extraWeeks = abs($weekOccurrence) - 1;

            return $lastOccurrence->sub(new \DateInterval("P{$extraWeeks}W"))->setTime($hour, $minute, 0);
        }

        $firstDay = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $currentDow = (int) $firstDay->format('w');
        $daysToAdd = ($weekDay - $currentDow + 7) % 7;
        $firstOccurrence = $firstDay->add(new \DateInterval("P{$daysToAdd}D"));
        $nthOccurrence = $firstOccurrence->add(new \DateInterval('P' . (($weekOccurrence - 1) * 7) . 'D'));

        // If we overshot into the next month (e.g., 5th Sunday doesn't exist), fall back to last.
        if ((int) $nthOccurrence->format('n') !== $month) {
            return $this->monthWeekdayOccurrence($year, $month, -1, $weekDay, $hour, $minute);
        }

        return $nthOccurrence->setTime($hour, $minute, 0);
    }

    // -------------------------------------------------------------------------
    // Yearly
    // -------------------------------------------------------------------------

    /**
     * @throws \DivisionByZeroError
     * @throws \DateMalformedStringException
     */
    private function computeNextYearly(
        \DateTimeImmutable $lastCompleted,
        \DateTimeImmutable $afterDate,
        int $intervalYears,
        int $month,
        int $day,
        int $hour,
        int $minute,
    ): \DateTimeImmutable {
        $anchorYear = (int) $lastCompleted->format('Y');

        $candidate = $this->yearlyOccurrence($anchorYear, $month, $day, $hour, $minute);
        if ($candidate <= $lastCompleted) {
            $candidate = $this->yearlyOccurrence($anchorYear + $intervalYears, $month, $day, $hour, $minute);
        }

        if ($candidate > $afterDate) {
            return $candidate;
        }

        $yearsDiff = (int) $afterDate->format('Y') - (int) $candidate->format('Y');
        $skip = max(0, (int) floor($yearsDiff / $intervalYears) - 1);
        if ($skip > 0) {
            $candidate = $this->yearlyOccurrence((int) $candidate->format('Y') + $skip * $intervalYears, $month, $day, $hour, $minute);
        }

        while ($candidate <= $afterDate) {
            $candidate = $this->yearlyOccurrence((int) $candidate->format('Y') + $intervalYears, $month, $day, $hour, $minute);
        }

        return $candidate;
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function yearlyOccurrence(int $year, int $month, int $day, int $hour, int $minute): \DateTimeImmutable
    {
        $maxDay = (int) (new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month)))->format('t');

        return new \DateTimeImmutable(
            sprintf('%d-%02d-%02d %02d:%02d:00', $year, $month, min($day, $maxDay), $hour, $minute)
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{int, int}
     */
    private function addMonths(int $year, int $month, int $months): array
    {
        $total = $year * 12 + $month - 1 + $months;

        return [(int) floor($total / 12), ($total % 12) + 1];
    }
}
