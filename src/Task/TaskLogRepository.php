<?php

namespace App\Task;

use App\Task\Entity\TaskLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\User\Entity\User;
use App\Task\Entity\Task;
use Doctrine\ORM\Query\Expr\Join;
use App\Household\Entity\Household;

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
}
