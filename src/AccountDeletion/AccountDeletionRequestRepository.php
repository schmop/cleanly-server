<?php

declare(strict_types=1);

namespace App\AccountDeletion;

use App\AccountDeletion\Entity\AccountDeletionRequest;
use App\Persistence\PersistenceException;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountDeletionRequest>
 */
class AccountDeletionRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountDeletionRequest::class);
    }

    public function findByUuid(string $uuid): ?AccountDeletionRequest
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findByUser(User $user): ?AccountDeletionRequest
    {
        return $this->findOneBy(['user' => $user]);
    }

    /**
     * @throws PersistenceException
     */
    public function save(AccountDeletionRequest $request): void
    {
        PersistenceException::persistAndFlush($this->getEntityManager(), $request);
    }

    /**
     * @throws PersistenceException
     */
    public function remove(AccountDeletionRequest $request): void
    {
        PersistenceException::removeAndFlush($this->getEntityManager(), $request);
    }
}
