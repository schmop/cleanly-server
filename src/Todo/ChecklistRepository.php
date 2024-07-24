<?php

namespace App\Todo;

use App\Todo\Entity\Checklist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Checklist>
 */
class ChecklistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Checklist::class);
    }

    public function findByUuid(string $uuid): ?Checklist
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function save(Checklist $checklist): void
    {
        $em = $this->getEntityManager();
        $em->persist($checklist);
        $em->flush();
    }

    public function remove(Checklist $checklist): void
    {
        $em = $this->getEntityManager();
        $em->remove($checklist);
        $em->flush();
    }
}
