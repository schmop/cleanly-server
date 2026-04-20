<?php

namespace App\Analytics;

use App\Analytics\Entity\UsageLog;
use App\Persistence\PersistenceException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UsageLog>
 */
final class UsageLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UsageLog::class);
    }

    /**
     * @throws PersistenceException
     */
    public function save(UsageLog $usageLog): void
    {
        PersistenceException::persistAndFlush($this->getEntityManager(), $usageLog);
    }

    /**
     * @return UsageLog[]
     */
    public function findAllByType(ActivityType $type): array
    {
        return $this->findBy(['activityType' => $type]);
    }
}
