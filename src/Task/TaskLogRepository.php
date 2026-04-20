<?php

namespace App\Task;

use App\Household\Entity\Household;
use App\Persistence\PersistenceException;
use App\Phunctional\Statistics;
use App\Task\Entity\Task;
use App\Task\Entity\TaskLog;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use function Lambdish\Phunctional\filter;
use function Lambdish\Phunctional\map;

/**
 * @extends ServiceEntityRepository<TaskLog>
 */
class TaskLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskLog::class);
    }

    /**
     * @return TaskLog[]
     */
    public function findByTask(Task $task): array
    {
        return $this->findBy(['task' => $task]);
    }

    public function findLastByTaskAndUser(Task $task, User $user): ?TaskLog
    {
        return $this->findOneBy(['task' => $task, 'user' => $user], ['timestamp' => 'DESC']);
    }

    public function findLastByTask(Task $task): ?TaskLog
    {
        return $this->findOneBy(['task' => $task], ['timestamp' => 'DESC']);
    }

    /**
     * @return TaskLog[]
     */
    public function findByHouseholdPaginated(Household $household, ?string $fromId): array
    {
        $qb = $this->createQueryBuilder('l');
        $qb
            ->innerJoin('l.task', 't')
            ->innerJoin('t.household', 'h')
            ->where('h.id = :household')
            ->orderBy('l.timestamp', 'DESC')
            ->setParameter(':household', $household->getId())
            ->setMaxResults(20);
        if (null !== $fromId) {
            /**
             * Subqueries in Doctrine are working best as DQLs
             * @link https://stackoverflow.com/a/58567069
             */
            $qb
                ->andWhere(
                    $qb->expr()->lt(
                        'l.timestamp',
                        '(' .
                        $this->createQueryBuilder('l2')
                            ->select('l2.timestamp')
                            ->where('l2.uuid = :fromId')
                            ->getDQL()
                        . ')',
                    ),
                )
                ->setParameter(':fromId', $fromId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return TaskLog[]
     */
    public function findByHousehold(Household $household): array
    {
        $qb = $this->createQueryBuilder('l');
        $qb
            ->innerJoin('l.task', 't')
            ->innerJoin('t.household', 'h')
            ->where('h.id = :household')
            ->orderBy('l.timestamp', 'ASC')
            ->setParameter(':household', $household->getId());

        return $qb->getQuery()->getResult();
    }

    /**
     * @return TaskLog[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    /**
     * @throws PersistenceException
     */
    public function save(TaskLog $tasklog): void
    {
        PersistenceException::persistAndFlush($this->getEntityManager(), $tasklog);
    }

    /**
     * @throws PersistenceException
     */
    public function remove(TaskLog $taskLog): void
    {
        PersistenceException::removeAndFlush($this->getEntityManager(), $taskLog);
    }

    /**
     * @return array<TaskId, array<UserId, int>>
     */
    public function getUserParticipations(Household $household): array
    {
        /** @param TaskLog[] $logs */
        return map(function (array $logs) {
            $userParticipations = [];
            foreach ($logs as $log) {
                $userId = $log->getUser()?->getId();
                if (null === $userId) {
                    continue;
                }
                if (!array_key_exists($userId, $userParticipations)) {
                    $userParticipations[$userId] = 0;
                }
                $userParticipations[$userId]++;
            }

            return $userParticipations;
        }, $this->getLogsPerTaskFromHousehold($household));
    }

    /**
     * @return array<
     *      TaskId,
     *      array{
     *          average: float,
     *          min: int,
     *          max: int,
     *      }
     *  >
     * @throws \Webmozart\Assert\InvalidArgumentException
     */
    public function getDurationStats(Household $household): array
    {
        /** @param TaskLog[] $logs */
        return map(function (array $logs) {
            $timestamps = map(fn(TaskLog $log) => $log->getTimestamp()->getTimestamp(), $logs);
            $deltas = Statistics::delta($timestamps);

            return [
                'average' => Statistics::average($deltas),
                'min' => Statistics::min($deltas),
                'max' => Statistics::max($deltas),
                'num' => count($deltas),
            ];
        }, $this->getLogsPerTaskFromHousehold($household));
    }

    /**
     * @return array<TaskId, TaskLog[]>
     */
    private function getLogsPerTaskFromHousehold(Household $household): array
    {
        $logsPerTask = [];
        foreach ($this->findByHousehold($household) as $log) {
            $taskId = $log->getTask()->getId();
            if (!array_key_exists($taskId, $logsPerTask)) {
                $logsPerTask[$taskId] = [];
            }
            $logsPerTask[$taskId][] = $log;
        }

        return $logsPerTask;
    }

    public function getNextAssignmentRotation(Task $task): ?User
    {
        $qb = $this->createQueryBuilder('l');

        $rotationOrders = $qb
            ->select('u.id, MAX(l.timestamp) as lastDone')
            ->leftJoin('l.user', 'u')
            ->where('l.task = :task')
            ->groupBy('u.id')
            ->orderBy('lastDone', 'DESC')
            ->setParameter(':task', $task)
            ->getQuery()
            ->getResult();

        $members = $task->getHousehold()->getMembers()->toArray();
        foreach ($rotationOrders as $rotationOrder) {
            if (count($members) <= 1) {
                break;
            }
            $members = filter(
                fn(User $member) => $member->getId() !== $rotationOrder['id'],
                $members,
            );
        }

        return array_shift($members) ?? null;
    }
}
