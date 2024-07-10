<?php

namespace App\Todo;

use App\Todo\Entity\Todo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Todo|null find($id, $lockMode = null, $lockVersion = null)
 * @method Todo|null findOneBy(array $criteria, array $orderBy = null)
 * @method Todo[]    findAll()
 * @method Todo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @extends ServiceEntityRepository<Todo>
 */
class TodoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Todo::class);
    }

    public function findByUuid(string $uuid): ?Todo
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }


    public function findByNextUuid(?string $nextUuid, Todo $butNotThis): ?Todo
    {
        $qb = $this->createQueryBuilder('t');

        if (null === $nextUuid) {
            $qb
                ->where('t.nextUuid IS NULL');
        } else {
            $qb
                ->where('t.nextUuid = :nextUuid')
                ->setParameter(':nextUuid', $nextUuid);
        }
        $qb
            ->andWhere('t.checklist = :checklist')
            ->andWhere('t.uuid <> :notThis')
            ->setParameter(':checklist', $butNotThis->getChecklist())
            ->setParameter(':notThis', $butNotThis->getUuid())
            ->setMaxResults(1);

        /**
         * In order for phpstan to infer the correct return type of getOneOrNullResult
         * it needs to explicitly have HYDRATE_OBJECT set
         * @link https://github.com/phpstan/phpstan-doctrine/issues/271
         * */
        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_OBJECT);
    }

    public function addToEndOfList(Todo $todo): void
    {
        $endOfList = $this->findByNextUuid(null, $todo);
        if (null === $endOfList) {
            return; // i am the list now
        }
        $endOfList->setNext($todo->getUuid());
        $this->getEntityManager()->flush();
    }

    /**
     * @throws InconsistentChecklistEventException
     */
    public function moveBefore(Todo $todo, ?string $beforeUuid): void
    {
        $this->removeOutOfList($todo);
        if (null === $beforeUuid) {
            $this->addToEndOfList($todo);
            return;
        }
        $insertBefore = $this->findByUuid($beforeUuid);
        if (null === $insertBefore) {
            throw new InconsistentChecklistEventException('Could not find event to insert after');
        }
        if ($todo->getUuid() === $beforeUuid) {
            throw new InconsistentChecklistEventException("Cannot sort checklist entries to themselves!");
        }
        $prevInsertBefore = $this->findByNextUuid($beforeUuid, $todo);
        if (null !== $prevInsertBefore) {
            $prevInsertBefore->setNext(null);
            $this->getEntityManager()->flush();
            $prevInsertBefore->setNext($todo->getUuid());
        }
        $todo->setNext($insertBefore->getUuid());
        $this->getEntityManager()->flush();
    }

    public function removeOutOfList(Todo $todo): void
    {
        $before = $this->findByNextUuid($todo->getUuid(), $todo);
        $next = $todo->getNext();
        $todo->setNext(null);
        $this->getEntityManager()->flush();
        if (null !== $before) {
            $before->setNext($next);
            $this->getEntityManager()->flush();
        }
    }
}
