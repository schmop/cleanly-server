<?php

namespace App\Household;

use App\Household\Entity\HouseholdRank;
use App\Persistence\PersistenceException;
use App\RankSort\RankSortableItem;
use App\RankSort\RankSortableItemRepositoryInterface;
use App\RankSort\RankSortableList;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HouseholdRank>
 * @implements RankSortableItemRepositoryInterface<HouseholdRank>
 */
class HouseholdRankRepository extends ServiceEntityRepository implements RankSortableItemRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HouseholdRank::class);
    }

    public function findByUuid(string $uuid): ?RankSortableItem
    {
        [$userId, $householdId] = self::parseCompositeUuid($uuid);
        if ($userId === null || $householdId === null) {
            return null;
        }

        return $this->findOneBy(['user' => $userId, 'household' => $householdId]);
    }

    /**
     * @throws PersistenceException
     */
    public function save(RankSortableItem $item): void
    {
        PersistenceException::persistAndFlush($this->getEntityManager(), $item);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findFirst(RankSortableList $list): RankSortableItem|null
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->where('r.user = :user')
            ->orderBy('r.sortRank', 'ASC')
            ->setMaxResults(1)
            ->setParameter(':user', $list)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findLast(RankSortableList $list): RankSortableItem|null
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->where('r.user = :user')
            ->orderBy('r.sortRank', 'DESC')
            ->setMaxResults(1)
            ->setParameter(':user', $list)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findAfter(RankSortableList $list, string $afterThisUuid): RankSortableItem|null
    {
        [$userId, $householdId] = self::parseCompositeUuid($afterThisUuid);
        if ($userId === null || $householdId === null) {
            return null;
        }

        $qb = $this->createQueryBuilder('r');
        $qb
            ->where('r.user = :user')
            ->andWhere('r.sortRank > (SELECT r2.sortRank FROM App\Household\Entity\HouseholdRank r2 WHERE r2.user = :afterUser AND r2.household = :afterHousehold)')
            ->orderBy('r.sortRank', 'ASC')
            ->setMaxResults(1)
            ->setParameter(':user', $list)
            ->setParameter(':afterUser', $userId)
            ->setParameter(':afterHousehold', $householdId)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findBefore(RankSortableList $list, string $beforeThisUuid): RankSortableItem|null
    {
        [$userId, $householdId] = self::parseCompositeUuid($beforeThisUuid);
        if ($userId === null || $householdId === null) {
            return null;
        }

        $qb = $this->createQueryBuilder('r');
        $qb
            ->where('r.user = :user')
            ->andWhere('r.sortRank < (SELECT r2.sortRank FROM App\Household\Entity\HouseholdRank r2 WHERE r2.user = :beforeUser AND r2.household = :beforeHousehold)')
            ->orderBy('r.sortRank', 'DESC')
            ->setMaxResults(1)
            ->setParameter(':user', $list)
            ->setParameter(':beforeUser', $userId)
            ->setParameter(':beforeHousehold', $householdId)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    public static function buildCompositeUuid(User $user, int $householdId): string
    {
        return $user->getId() . ':' . $householdId;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private static function parseCompositeUuid(string $uuid): array
    {
        $parts = explode(':', $uuid, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
            return [null, null];
        }

        return [(int)$parts[0], (int)$parts[1]];
    }
}
