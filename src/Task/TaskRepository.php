<?php

namespace App\Task;

use App\Task\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function findById(string $id): ?Task
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function save(Task $task): void
    {
        $em = $this->getEntityManager();
        $em->persist($task);
        $em->flush();
    }

    public function remove(Task $task): void
    {
        $em = $this->getEntityManager();
        $em->remove($task);
        $em->flush();
    }
}
