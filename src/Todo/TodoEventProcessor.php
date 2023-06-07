<?php

namespace App\Todo;

use App\Todo\Entity\Checklist;
use App\Todo\Entity\Todo;
use Doctrine\ORM\EntityManagerInterface;

readonly class TodoEventProcessor
{
    public function __construct(
        private TodoRepository         $todoRepository,
        private EntityManagerInterface $entityManager,
    ) {
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
        $this->todoRepository->addToEndOfList($todo);
        $checklist->getChecklist()->add($todo);
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
        $this->todoRepository->moveBefore($todo, $event->data);
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
        $this->todoRepository->removeOutOfList($todo);
        $checklist->getChecklist()->removeElement($todo);
        $this->entityManager->flush();
    }
}
