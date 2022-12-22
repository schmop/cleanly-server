<?php

namespace App\Task;

use App\Task\Entity\TaskLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\User\Entity\User;
use App\Task\Entity\Task;
use App\Household\Entity\Household;
use App\Phunctional\Statistics;

use function Lambdish\Phunctional\map;

/**
 * @method TaskLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaskLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method TaskLog[]    findAll()
 * @method TaskLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
            ->setMaxResults(20)
        ;
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
                ->setParameter(':fromId', $fromId)
            ;
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
            ->setParameter(':household', $household->getId())
        ;

        return $qb->getQuery()->getResult();
    }

    /**
     * @return TaskLog[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    public function save(TaskLog $tasklog): void
    {
        $em = $this->getEntityManager();
        $em->persist($tasklog);
        $em->flush();
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
     *          average: int,
     *          min: int,
     *          max: int,
     *      }
     *  >
     */
    public function getDurationStats(Household $household): array
    {
        /** @param TaskLog[] $logs */
        return map(function (array $logs) {
            $timestamps = map(fn (TaskLog $log) => $log->getTimestamp()->getTimestamp(), $logs);
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
        $num = 0;
        foreach ($this->findByHousehold($household) as $log) {
            $taskId = $log->getTask()->getId();
            if (!array_key_exists($taskId, $logsPerTask)) {
                $logsPerTask[$taskId] = [];
            }
            $logsPerTask[$taskId][] = $log;
            $num++;
        }

        return $logsPerTask;
    }
}
