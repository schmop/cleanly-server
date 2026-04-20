<?php

namespace App\Registration;

use App\Persistence\PersistenceException;
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

    /**
     * @throws PersistenceException
     */
    public function save(Registration $registration): void
    {
        PersistenceException::persistAndFlush($this->getEntityManager(), $registration);
    }

    /**
     * @throws PersistenceException
     */
    public function remove(Registration $registration): void
    {
        PersistenceException::removeAndFlush($this->getEntityManager(), $registration);
    }
}
