<?php

namespace App\Todo;

use App\RankSort\RankSortableItem;
use App\RankSort\RankSortableItemRepositoryInterface;
use App\RankSort\RankSortableList;
use App\Todo\Entity\Todo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Todo>
 * @implements RankSortableItemRepositoryInterface<Todo>
 */
class TodoRepository extends ServiceEntityRepository implements RankSortableItemRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Todo::class);
    }

    public function findByUuid(string $uuid): ?Todo
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findFirst(RankSortableList $list): RankSortableItem|null
    {
        $qb = $this->createQueryBuilder('t');

        $qb
            ->where('t.checklist = :checklist')
            ->orderBy('t.sortRank', 'ASC')
            ->setMaxResults(1)
            ->setParameter(':checklist', $list)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    public function findLast(RankSortableList $list): RankSortableItem|null
    {
        $qb = $this->createQueryBuilder('t');

        $qb
            ->where('t.checklist = :checklist')
            ->orderBy('t.sortRank', 'DESC')
            ->setMaxResults(1)
            ->setParameter(':checklist', $list)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    public function findAfter(RankSortableList $list, string $afterThisUuid): RankSortableItem|null
    {

        $qb = $this->createQueryBuilder('t');

        $qb
            ->where('t.checklist = :checklist')
            ->andWhere('t.sortRank > (SELECT t2.sortRank FROM App\Todo\Entity\Todo t2 WHERE t2.uuid = :afterThisUuid)')
            ->orderBy('t.sortRank', 'ASC')
            ->setMaxResults(1)
            ->setParameter(':checklist', $list)
            ->setParameter(':afterThisUuid', $afterThisUuid)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    public function findBefore(RankSortableList $list, string $beforeThisUuid): RankSortableItem|null
    {

        $qb = $this->createQueryBuilder('t');

        $qb
            ->where('t.checklist = :checklist')
            ->andWhere('t.sortRank < (SELECT t2.sortRank FROM App\Todo\Entity\Todo t2 WHERE t2.uuid = :beforeThisUuid)')
            ->orderBy('t.sortRank', 'DESC')
            ->setMaxResults(1)
            ->setParameter(':checklist', $list)
            ->setParameter(':beforeThisUuid', $beforeThisUuid)
        ;

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    public function save(RankSortableItem $item): void
    {
        $em = $this->getEntityManager();
        $em->persist($item);
        $em->flush();
    }
}
