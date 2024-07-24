<?php

namespace App\Registration;

use App\Registration\Entity\Registration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Registration>
 */
class RegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Registration::class);
    }

    public function findByUuid(string $uuid): ?Registration
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findByMail(string $mail): ?Registration
    {
        return $this->findOneBy(['mail' => $mail]);
    }

    public function save(Registration $registration): void
    {
        $em = $this->getEntityManager();
        $em->persist($registration);
        $em->flush();
    }

    public function remove(Registration $registration): void
    {
        $em = $this->getEntityManager();
        $em->remove($registration);
        $em->flush();
    }
}
