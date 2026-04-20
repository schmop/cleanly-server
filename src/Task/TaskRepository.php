<?php

namespace App\Task;

use App\Persistence\PersistenceException;
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

    /**
     * @throws PersistenceException
     */
    public function save(Task $task): void
    {
        PersistenceException::persistAndFlush($this->getEntityManager(), $task);
    }

    /**
     * @throws PersistenceException
     */
    public function remove(Task $task): void
    {
        PersistenceException::removeAndFlush($this->getEntityManager(), $task);
    }
}
