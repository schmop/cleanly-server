<?php

namespace App\User;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function save(UserSettings $userSettings): void
    {
        $em = $this->getEntityManager();
        $em->persist($userSettings);
        $em->flush();
    }

    public function remove(UserSettings $userSettings): void
    {
        $em = $this->getEntityManager();
        $em->remove($userSettings);
        $em->flush();
    }
}
