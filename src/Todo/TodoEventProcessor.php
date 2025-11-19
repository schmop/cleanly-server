<?php

namespace App\Todo;

use App\RankSort\ItemSorter;
use App\Todo\Entity\Checklist;
use App\Todo\Entity\Todo;
use Doctrine\ORM\EntityManagerInterface;

readonly class TodoEventProcessor
{
    /** @var ItemSorter<Todo> */
    private ItemSorter $sorter;

    public function __construct(
        private TodoRepository         $todoRepository,
        private EntityManagerInterface $entityManager,
    ) {
        $this->sorter = new ItemSorter($this->todoRepository);
    }

    /**
     * @param TodoEvent[] $events
     * @throws InconsistentChecklistEventException
     */
    public function process(array $events, Checklist $checklist): void
    {
        $this->entityManager->beginTransaction();
        try {
            foreach ($events as $event) {
                switch ($event->type) {
                    case TodoEvent::TYPE_CREATE:
                        $this->create($event, $checklist);
                        break;
                    case TodoEvent::TYPE_SORT:
                        $this->sort($event);
                        break;
                    case TodoEvent::TYPE_UPDATE:
                        $this->update($event);
                        break;
                    case TodoEvent::TYPE_CHECK:
                        $this->check($event, $checklist);
                        break;
                    case TodoEvent::TYPE_DELETE:
                        $this->delete($event, $checklist);
                        break;
                }
            }
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
        $this->entityManager->commit();
    }

    private function create(TodoEvent $event, Checklist $checklist): void
    {
        $todo = new Todo($event->uuid, $event->data ?? '', $checklist);
        $this->sorter->moveAtEnd($todo);
        $checklist->getChecklist()->add($todo);
        $this->entityManager->persist($todo);
        $this->entityManager->flush();
    }

    /**
     * @throws InconsistentChecklistEventException
     */
    private function update(TodoEvent $event): void
    {
        $todo = $this->todoRepository->findByUuid($event->uuid);
        if (null === $todo) {
            throw new InconsistentChecklistEventException("Cannot update checklist entries that don't exist!");
        }
        $todo->setContent($event->data ?? '');
        $this->entityManager->flush();
    }

    /**
     * @throws InconsistentChecklistEventException
     */
    private function sort(TodoEvent $event): void
    {
        $todo = $this->todoRepository->findByUuid($event->uuid);
        if (null === $todo) {
            throw new InconsistentChecklistEventException("Cannot sort checklist entries that don't exist!");
        }
        $this->sorter->moveBefore($todo, $event->data);
    }

    /**
     * @throws InconsistentChecklistEventException
     */
    private function check(TodoEvent $event, Checklist $checklist): void
    {
        $todo = $this->todoRepository->findByUuid($event->uuid);
        if (null === $todo) {
            throw new InconsistentChecklistEventException("Cannot check checklist entries that don't exist!");
        }
        $todo->setCheckedAt(null === $event->data ? null : new \DateTimeImmutable('@' . intval($event->data)));
        $this->entityManager->flush();
    }

    /**
     * @throws InconsistentChecklistEventException
     */
    private function delete(TodoEvent $event, Checklist $checklist): void
    {
        $todo = $this->todoRepository->findByUuid($event->uuid);
        if (null === $todo) {
            throw new InconsistentChecklistEventException("Cannot delete checklist entries that don't exist!");
        }
        $checklist->getChecklist()->removeElement($todo);
        $this->entityManager->remove($todo);
        $this->entityManager->flush();
    }
}
