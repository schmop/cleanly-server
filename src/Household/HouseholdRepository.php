<?php

namespace App\Household;

use App\Household\Entity\Household;
use App\Persistence\PersistenceException;
use App\Todo\Entity\Checklist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Household>
 */
class HouseholdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Household::class);
    }

    /**
     * @return array<int, array{
     *      user: int,
     *      stars: int|numeric-string|null,
     * }>
     */
    public function retrieveStars(Household $household): array
    {
        $qb = $this->createQueryBuilder('h');
        $qb
            ->select('u.id as user, SUM(l.stars) as stars')
            ->innerJoin('h.tasks', 't')
            ->innerJoin('t.logs', 'l')
            ->innerJoin('l.user', 'u')
            ->where('h.id = :householdId')
            ->groupBy('u.id')
            ->setParameter('householdId', $household->getId());

        return $qb->getQuery()->getResult();
    }

    /**
     * @throws PersistenceException
     */
    public function save(Household $household): void
    {
        PersistenceException::persistAndFlush($this->getEntityManager(), $household);
    }
}
