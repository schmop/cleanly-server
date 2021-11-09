<?php

namespace App\Repository;

use App\Entity\HouseholdInvite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method HouseholdInvite|null find($id, $lockMode = null, $lockVersion = null)
 * @method HouseholdInvite|null findOneBy(array $criteria, array $orderBy = null)
 * @method HouseholdInvite[]    findAll()
 * @method HouseholdInvite[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HouseholdInviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HouseholdInvite::class);
    }
}
