<?php

namespace App\Push;

use App\Push\Entity\Device;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use App\Entity\Household;

/**
 * @method Device|null find($id, $lockMode = null, $lockVersion = null)
 * @method Device|null findOneBy(array $criteria, array $orderBy = null)
 * @method Device[]    findAll()
 * @method Device[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
        return $this->findAll(['user' => $user]);
    }

    public function findByDeviceId(string $deviceId): null|Device
    {
        return $this->findOneBy(['id' => $deviceId]);
    }

    /**
     * @return string[] All the pushIds to directly publish
     */
    public function findByHousehold(Household $household): array
    {
        $qb = $this->createQueryBuilder('d');
        $qb
            ->select('d.pushId')
            ->innerJoin('d.user', 'u')
            ->innerJoin('u.households', 'h')
            ->where('h.id = :household')
            ->setParameter('household', $household->getId())
        ;

        return $qb->getQuery()->getSingleColumnResult();
    }

    /**
     * @param User[] $users
     * @return string[] All the pushIds to directly publish
     */
    public function findByUsers(array $users): array
    {
        $userIds = array_map(static function (User $user) { return $user->getId(); }, $users);

        $qb = $this->createQueryBuilder('d');
        $qb
            ->select('d.pushId')
            ->innerJoin('d.user', 'u')
            ->where('u.id in (:users)')
            ->setParameter('users', $userIds)
        ;

        return $qb->getQuery()->getSingleColumnResult();
    }
}
