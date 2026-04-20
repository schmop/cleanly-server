<?php

namespace App\User;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Persistence\PersistenceException;
use Doctrine\Persistence\ManagerRegistry;
use App\User\Entity\UserSettings;

/**
 * @extends ServiceEntityRepository<UserSettings>
 */
class UserSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSettings::class);
    }

    /**
     * @throws PersistenceException
     */
    public function save(UserSettings $userSettings): void
    {
        PersistenceException::persistAndFlush($this->getEntityManager(), $userSettings);
    }

    /**
     * @throws PersistenceException
     */
    public function remove(UserSettings $userSettings): void
    {
        PersistenceException::removeAndFlush($this->getEntityManager(), $userSettings);
    }
}
