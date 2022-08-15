<?php

namespace App\Household;

use App\Household\Entity\Household;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Household|null find($id, $lockMode = null, $lockVersion = null)
 * @method Household|null findOneBy(array $criteria, array $orderBy = null)
 * @method Household[]    findAll()
 * @method Household[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HouseholdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Household::class);
    }

    /**
     * @return array<string, int>
     */
    public function retrieveStars(Household $household): array
    {
        $qb = $this->createQueryBuilder('h');
        $qb
            ->select('u.id as user, SUM(t.stars) as stars')
            ->innerJoin('h.tasks', 't')
            ->innerJoin('t.logs', 'l')
            ->innerJoin('l.user', 'u')
            ->where('h.id = :householdId')
            ->groupBy('u.id')
            ->setParameter('householdId', $household->getId())
        ;

        return $qb->getQuery()->getResult();
    }
}