<?php

namespace App\Analytics;

use App\Analytics\Entity\UsageLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class UsageLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UsageLog::class);
    }

    public function save(UsageLog $usageLog): void
    {
        $em = $this->getEntityManager();
        $em->persist($usageLog);
        $em->flush();
    }

    /**
     * @return UsageLog[]
     */
    public function findAllByType(ActivityType $type): array
    {
        return $this->findBy(['activityType' => $type]);
    }
}
