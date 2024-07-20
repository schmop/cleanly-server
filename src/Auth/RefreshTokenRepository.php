<?php

namespace App\Auth;

use App\Auth\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function save(RefreshToken $refreshToken): void
    {
        $em = $this->getEntityManager();
        $em->persist($refreshToken);
        $em->flush();
    }

    public function findByToken(string $token): ?RefreshToken
    {
        return $this->findOneBy(['token' => $token]);
    }
}
