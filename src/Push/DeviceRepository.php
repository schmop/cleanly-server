<?php

namespace App\Push;

use App\Push\Entity\Device;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\User\Entity\User;
use App\Household\Entity\Household;

/**
 * @extends ServiceEntityRepository<Device>
 */
class DeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Device::class);
    }

    /**
     * @return Device[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    public function findByDeviceId(string $deviceId): null|Device
    {
        return $this->findOneBy(['id' => $deviceId]);
    }

    /**
     * @return Device[] All the pushIds to directly publish
     */
    public function findByHousehold(Household $household): array
    {
        $qb = $this->createQueryBuilder('d');
        $qb
            ->innerJoin('d.user', 'u')
            ->innerJoin('u.households', 'h')
            ->where('h.id = :household')
            ->setParameter('household', $household->getId())
        ;

        return $qb->getQuery()->getResult();
    }

    /**
     * @param User[] $users
     * @return Device[] All the pushIds to directly publish
     */
    public function findByUsers(array $users): array
    {
        $userIds = array_map(static function (User $user) { return $user->getId(); }, $users);

        $qb = $this->createQueryBuilder('d');
        $qb
            ->innerJoin('d.user', 'u')
            ->where('u.id in (:users)')
            ->setParameter('users', $userIds)
        ;

        return $qb->getQuery()->getResult();
    }
}
