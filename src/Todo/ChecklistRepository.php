<?php

namespace App\Todo;

use App\Persistence\PersistenceException;
use App\RankSort\RankSortableItem;
use App\RankSort\RankSortableItemRepositoryInterface;
use App\RankSort\RankSortableList;
use App\Todo\Entity\Checklist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Checklist>
 * @implements RankSortableItemRepositoryInterface<Checklist>
 */
class ChecklistRepository extends ServiceEntityRepository implements RankSortableItemRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Checklist::class);
    }

    public function findByUuid(string $uuid): ?Checklist
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    /**
     * @throws PersistenceException
     */
    public function remove(Checklist $checklist): void
    {
        PersistenceException::removeAndFlush($this->getEntityManager(), $checklist);
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findFirst(RankSortableList $list): RankSortableItem|null
    {
        $qb = $this->createQueryBuilder('c');

        $qb
            ->where('c.household = :household')
            ->orderBy('c.sortRank', 'ASC')
            ->setMaxResults(1)
            ->setParameter(':household', $list)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findLast(RankSortableList $list): RankSortableItem|null
    {
        $qb = $this->createQueryBuilder('c');

        $qb
            ->where('c.household = :household')
            ->orderBy('c.sortRank', 'DESC')
            ->setMaxResults(1)
            ->setParameter(':household', $list)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    /**
     * @throws PersistenceException
     */
    public function save(RankSortableItem $item): void
    {
        PersistenceException::persistAndFlush($this->getEntityManager(), $item);
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findAfter(RankSortableList $list, string $afterThisUuid): RankSortableItem|null
    {
        $qb = $this->createQueryBuilder('c');

        $qb
            ->where('c.household = :household')
            ->andWhere('c.sortRank > (SELECT t2.sortRank FROM App\Todo\Entity\Todo t2 WHERE t2.uuid = :afterThisUuid)')
            ->orderBy('c.sortRank', 'ASC')
            ->setMaxResults(1)
            ->setParameter(':household', $list)
            ->setParameter(':afterThisUuid', $afterThisUuid)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findBefore(RankSortableList $list, string $beforeThisUuid): RankSortableItem|null
    {
        $qb = $this->createQueryBuilder('c');

        $qb
            ->where('c.household = :household')
            ->andWhere('c.sortRank < (SELECT t2.sortRank FROM App\Todo\Entity\Todo t2 WHERE t2.uuid = :beforeThisUuid)')
            ->orderBy('c.sortRank', 'DESC')
            ->setMaxResults(1)
            ->setParameter(':household', $list)
            ->setParameter(':beforeThisUuid', $beforeThisUuid)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }
}
