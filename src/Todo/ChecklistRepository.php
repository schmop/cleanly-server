<?php

namespace App\Todo;

use App\Todo\Entity\Checklist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Checklist|null find($id, $lockMode = null, $lockVersion = null)
 * @method Checklist|null findOneBy(array $criteria, array $orderBy = null)
 * @method Checklist[]    findAll()
 * @method Checklist[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
        $em->flush();
    }

    public function remove(Checklist $checklist): void
    {
        $em = $this->getEntityManager();
        $em->flush();
    }
}
