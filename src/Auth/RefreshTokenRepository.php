<?php

namespace App\Auth;

use App\Auth\Entity\RefreshToken;
use App\Persistence\PersistenceException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
final class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    /**
     * @throws PersistenceException
     */
    public function save(RefreshToken $refreshToken): void
    {
        PersistenceException::persistAndFlush($this->getEntityManager(), $refreshToken);
    }

    public function findByToken(string $token): ?RefreshToken
    {
        return $this->findOneBy(['token' => $token]);
    }
}
