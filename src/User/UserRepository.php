<?php

namespace App\User;

use App\Persistence\PersistenceException;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     * @throws PersistenceException
     * @throws \Symfony\Component\Security\Core\Exception\UnsupportedUserException
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        PersistenceException::persistAndFlush($this->getEntityManager(), $user);
    }

    public function findByMail(string $mail): ?User
    {
        return $this->findOneBy(['mail' => $mail]);
    }

    /**
     * @return User[]
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.name) LIKE LOWER(:search)')
            ->setParameter('search', '%' . $query . '%')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @throws PersistenceException
     */
    public function save(User $user): void
    {
        PersistenceException::persistAndFlush($this->getEntityManager(), $user);
    }

    /**
     * @throws PersistenceException
     */
    public function remove(User $user): void
    {
        PersistenceException::removeAndFlush($this->getEntityManager(), $user);
    }
}
