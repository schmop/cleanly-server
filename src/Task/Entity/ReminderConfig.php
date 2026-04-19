<?php

declare(strict_types=1);

namespace App\Task\Entity;

use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reminder_config')]
class ReminderConfig implements \JsonSerializable
{
    public const string TYPE_DAILY = 'daily';
    public const string TYPE_WEEKLY = 'weekly';
    public const string TYPE_MONTHLY_DAY = 'monthly_day';
    public const string TYPE_MONTHLY_WEEKDAY = 'monthly_weekday';
    public const string TYPE_YEARLY = 'yearly';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\OneToOne(inversedBy: 'reminderConfig', targetEntity: Task::class)]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    #[ORM\Column(type: 'string', length: 32)]
    public string $type;

    #[ORM\Column(type: 'integer', name: 'step_interval')]
    public int $interval;

    #[ORM\Column(type: 'string', length: 5, name: 'reminder_time')]
    public string $time;

    /**
     * Bitmask of weekdays. Bit 0 = Mon, bit 1 = Tue … bit 5 = Sat, bit 6 = Sun.
     * Valid range: 1..127 (at least one day).
     */
    #[ORM\Column(type: 'smallint', nullable: true, name: 'days_of_week')]
    public ?int $daysOfWeek;

    #[ORM\Column(type: 'smallint', nullable: true, name: 'month_day')]
    public ?int $monthDay;

    #[ORM\Column(type: 'smallint', nullable: true, name: 'week_occurrence')]
    public ?int $weekOccurrence;

    #[ORM\Column(type: 'smallint', nullable: true, name: 'week_day')]
    public ?int $weekDay;

    #[ORM\Column(type: 'smallint', nullable: true, name: 'year_month')]
    public ?int $month;

    #[ORM\Column(type: 'smallint', nullable: true, name: 'year_day')]
    public ?int $day;

    private function __construct(
        string $type,
        int $interval,
        string $time,
        ?int $daysOfWeek = null,
        ?int $monthDay = null,
        ?int $weekOccurrence = null,
        ?int $weekDay = null,
        ?int $month = null,
        ?int $day = null,
    ) {
        $this->type = $type;
        $this->interval = $interval;
        $this->time = $time;
        $this->daysOfWeek = $daysOfWeek;
        $this->monthDay = $monthDay;
        $this->weekOccurrence = $weekOccurrence;
        $this->weekDay = $weekDay;
        $this->month = $month;
        $this->day = $day;
    }

    public static function daily(int $interval, string $time): self
    {
        return new self(self::TYPE_DAILY, $interval, $time);
    }

    /**
     * @param int $daysOfWeek bitmask: bit 0=Mon, 1=Tue, 2=Wed, 3=Thu, 4=Fri, 5=Sat, 6=Sun
     */
    public static function weekly(int $interval, int $daysOfWeek, string $time): self
    {
        return new self(self::TYPE_WEEKLY, $interval, $time, $daysOfWeek);
    }

    public static function monthlyDay(int $interval, int $monthDay, string $time): self
    {
        return new self(self::TYPE_MONTHLY_DAY, $interval, $time, null, $monthDay);
    }

    public static function monthlyWeekday(int $interval, int $weekOccurrence, int $weekDay, string $time): self
    {
        return new self(self::TYPE_MONTHLY_WEEKDAY, $interval, $time, null, null, $weekOccurrence, $weekDay);
    }

    public static function yearly(int $interval, int $month, int $day, string $time): self
    {
        return new self(self::TYPE_YEARLY, $interval, $time, null, null, null, null, $month, $day);
    }

    /**
     * @throws UnexpectedJsonException
     */
    public static function fromJson(Json $json): self
    {
        $type = $json->string('type');
        $interval = $json->int('interval');
        $time = $json->string('time');

        return match ($type) {
            self::TYPE_DAILY => self::daily($interval, $time),
            self::TYPE_WEEKLY => self::weekly($interval, $json->int('daysOfWeek'), $time),
            self::TYPE_MONTHLY_DAY => self::monthlyDay($interval, $json->int('monthDay'), $time),
            self::TYPE_MONTHLY_WEEKDAY => self::monthlyWeekday($interval, $json->int('weekOccurrence'), $json->int('weekDay'), $time),
            self::TYPE_YEARLY => self::yearly($interval, $json->int('month'), $json->int('day'), $time),
            default => throw new \InvalidArgumentException("Unknown reminder type: {$type}"),
        };
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function setTask(Task $task): void
    {
        $this->task = $task;
    }

    /**
     * Copy schedule fields from $other into this entity. Used when replacing
     * a Task's reminder — updating in place avoids a unique-constraint race
     * between the new INSERT and the old orphan DELETE on flush.
     */
    public function overwriteWith(self $other): void
    {
        $this->type = $other->type;
        $this->interval = $other->interval;
        $this->time = $other->time;
        $this->daysOfWeek = $other->daysOfWeek;
        $this->monthDay = $other->monthDay;
        $this->weekOccurrence = $other->weekOccurrence;
        $this->weekDay = $other->weekDay;
        $this->month = $other->month;
        $this->day = $other->day;
    }

    /**
     * @return array{type: string, interval: int, time: string, daysOfWeek?: int, monthDay?: int, weekOccurrence?: int, weekDay?: int, month?: int, day?: int}
     */
    public function jsonSerialize(): array
    {
        $data = [
            'type' => $this->type,
            'interval' => $this->interval,
            'time' => $this->time,
        ];
        if ($this->daysOfWeek !== null) {
            $data['daysOfWeek'] = $this->daysOfWeek;
        }
        if ($this->monthDay !== null) {
            $data['monthDay'] = $this->monthDay;
        }
        if ($this->weekOccurrence !== null) {
            $data['weekOccurrence'] = $this->weekOccurrence;
        }
        if ($this->weekDay !== null) {
            $data['weekDay'] = $this->weekDay;
        }
        if ($this->month !== null) {
            $data['month'] = $this->month;
        }
        if ($this->day !== null) {
            $data['day'] = $this->day;
        }

        return $data;
    }
}
