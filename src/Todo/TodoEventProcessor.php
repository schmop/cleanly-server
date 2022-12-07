<?php

namespace App\Todo;

use App\Household\Entity\Household;
use App\Todo\Entity\Todo;
use Doctrine\ORM\EntityManagerInterface;

class TodoEventProcessor
{
    public function __construct(
        private TodoRepository $todoRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param TodoEvent[] $events
     */
    public function process(array $events, Household $household): void
    {
        $this->entityManager->beginTransaction();
        try {
            foreach ($events as $event) {
                switch ($event->type) {
                    case TodoEvent::TYPE_CREATE:
                        $this->create($event, $household);
                        break;
                    case TodoEvent::TYPE_SORT:
                        $this->sort($event, $household);
                        break;
                    case TodoEvent::TYPE_UPDATE:
                        $this->update($event);
                        break;
                    case TodoEvent::TYPE_DELETE:
                        $this->delete($event, $household);
                        break;
                }
            }
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
        $this->entityManager->commit();
    }

    private function create(TodoEvent $event, Household $household): void
    {
        $todo = new Todo($event->uuid, $event->data ?? '', $household);
        $this->todoRepository->addToEndOfList($todo);
        $household->getChecklist()->add($todo);
        $this->entityManager->flush();
    }

    private function update(TodoEvent $event): void
    {
        $todo = $this->todoRepository->findByUuid($event->uuid);
        if (null === $todo) {
            throw new InconsistentChecklistEventException("Cannot update checklist entries that don't exist!");
        }
        $todo->setContent($event->data ?? '');
        $this->entityManager->flush();
    }

    private function sort(TodoEvent $event, Household $household): void
    {
        $todo = $this->todoRepository->findByUuid($event->uuid);
        if (null === $todo) {
            throw new InconsistentChecklistEventException("Cannot sort checklist entries that don't exist!");
        }
        $this->todoRepository->moveBefore($todo, $event?->data ?? null);
    }

    private function delete(TodoEvent $event, Household $household): void
    {
        $todo = $this->todoRepository->findByUuid($event->uuid);
        if (null === $todo) {
            throw new InconsistentChecklistEventException("Cannot delete checklist entries that don't exist!");
        }
        $this->todoRepository->removeOutOfList($todo);
        $household->getChecklist()->removeElement($todo);
        $this->entityManager->flush();
    }
}
