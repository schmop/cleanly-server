<?php

declare(strict_types=1);

namespace App\Tests\Todo;

use AlexCrawford\LexoRank\Rank;
use App\Household\Entity\Household;
use App\Household\Entity\HouseholdPrivilege;
use App\Household\ReassignmentStrategy;
use App\Tests\Controller\Privilege\PrivilegeFixtureTrait;
use App\Todo\Entity\Checklist;
use App\Todo\Entity\Todo;
use App\Todo\InconsistentChecklistEventException;
use App\Todo\TodoEvent;
use App\Todo\TodoEventProcessor;
use App\Todo\TodoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TodoEventProcessorTest extends WebTestCase
{
    use PrivilegeFixtureTrait;

    private TodoEventProcessor $processor;
    private TodoRepository $todoRepository;
    private Checklist $checklist;

    protected function setUp(): void
    {
        static::createClient();
        $this->initFixtures();

        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $this->checklist = $this->createChecklist($household);

        $this->todoRepository = static::getContainer()->get(TodoRepository::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->processor = new TodoEventProcessor($this->todoRepository, $em);
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupFixtures();
        } finally {
            parent::tearDown();
        }
    }

    public function testCreateAddsTodoToChecklist(): void
    {
        $event = new TodoEvent(TodoEvent::TYPE_CREATE, 'todo-1', $this->checklist->getUuid(), 'Buy milk');

        $this->processor->process([$event], $this->checklist);

        $stored = $this->todoRepository->findByUuid('todo-1');
        $this->assertNotNull($stored);
        $this->assertSame('Buy milk', $stored->getContent());
        $this->assertSame($this->checklist->getUuid(), $stored->getChecklist()->getUuid());
    }

    public function testCreateWithNullDataResultsInEmptyContent(): void
    {
        $event = new TodoEvent(TodoEvent::TYPE_CREATE, 'todo-empty', $this->checklist->getUuid(), null);

        $this->processor->process([$event], $this->checklist);

        $stored = $this->todoRepository->findByUuid('todo-empty');
        $this->assertNotNull($stored);
        $this->assertSame('', $stored->getContent());
    }

    public function testUpdateChangesContent(): void
    {
        $this->processor->process([
            new TodoEvent(TodoEvent::TYPE_CREATE, 'todo-u', $this->checklist->getUuid(), 'old'),
            new TodoEvent(TodoEvent::TYPE_UPDATE, 'todo-u', $this->checklist->getUuid(), 'new'),
        ], $this->checklist);

        $stored = $this->todoRepository->findByUuid('todo-u');
        $this->assertNotNull($stored);
        $this->assertSame('new', $stored->getContent());
    }

    public function testUpdateMissingTodoThrows(): void
    {
        $this->expectException(InconsistentChecklistEventException::class);
        $this->processor->process([
            new TodoEvent(TodoEvent::TYPE_UPDATE, 'no-such-uuid', $this->checklist->getUuid(), 'data'),
        ], $this->checklist);
    }

    public function testCheckSetsTimestampFromEpochSeconds(): void
    {
        $this->processor->process([
            new TodoEvent(TodoEvent::TYPE_CREATE, 'todo-c', $this->checklist->getUuid(), 'item'),
            new TodoEvent(TodoEvent::TYPE_CHECK, 'todo-c', $this->checklist->getUuid(), '1700000000'),
        ], $this->checklist);

        $stored = $this->todoRepository->findByUuid('todo-c');
        $this->assertNotNull($stored);
        $serialized = $stored->jsonSerialize();
        $this->assertSame(1700000000, $serialized['checked_at'] ?? null);
    }

    public function testUncheckClearsTimestamp(): void
    {
        $this->processor->process([
            new TodoEvent(TodoEvent::TYPE_CREATE, 'todo-uc', $this->checklist->getUuid(), 'item'),
            new TodoEvent(TodoEvent::TYPE_CHECK, 'todo-uc', $this->checklist->getUuid(), '1700000000'),
            new TodoEvent(TodoEvent::TYPE_CHECK, 'todo-uc', $this->checklist->getUuid(), null),
        ], $this->checklist);

        $stored = $this->todoRepository->findByUuid('todo-uc');
        $this->assertNotNull($stored);
        $serialized = $stored->jsonSerialize();
        $this->assertArrayHasKey('checked_at', $serialized);
        $this->assertNull($serialized['checked_at']);
    }

    public function testCheckMissingTodoThrows(): void
    {
        $this->expectException(InconsistentChecklistEventException::class);
        $this->processor->process([
            new TodoEvent(TodoEvent::TYPE_CHECK, 'missing', $this->checklist->getUuid(), '1700000000'),
        ], $this->checklist);
    }

    public function testDeleteRemovesTodo(): void
    {
        $this->processor->process([
            new TodoEvent(TodoEvent::TYPE_CREATE, 'todo-d', $this->checklist->getUuid(), 'item'),
            new TodoEvent(TodoEvent::TYPE_DELETE, 'todo-d', $this->checklist->getUuid(), null),
        ], $this->checklist);

        $this->assertNull($this->todoRepository->findByUuid('todo-d'));
    }

    public function testDeleteMissingTodoThrows(): void
    {
        $this->expectException(InconsistentChecklistEventException::class);
        $this->processor->process([
            new TodoEvent(TodoEvent::TYPE_DELETE, 'missing', $this->checklist->getUuid(), null),
        ], $this->checklist);
    }

    public function testSortMissingTodoThrows(): void
    {
        $this->expectException(InconsistentChecklistEventException::class);
        $this->processor->process([
            new TodoEvent(TodoEvent::TYPE_SORT, 'missing', $this->checklist->getUuid(), null),
        ], $this->checklist);
    }

    public function testCreateThenSortReordersByRank(): void
    {
        $this->processor->process([
            new TodoEvent(TodoEvent::TYPE_CREATE, 'a', $this->checklist->getUuid(), 'A'),
            new TodoEvent(TodoEvent::TYPE_CREATE, 'b', $this->checklist->getUuid(), 'B'),
            new TodoEvent(TodoEvent::TYPE_CREATE, 'c', $this->checklist->getUuid(), 'C'),
            // Move c before a -> order becomes c, a, b.
            new TodoEvent(TodoEvent::TYPE_SORT, 'c', $this->checklist->getUuid(), 'a'),
        ], $this->checklist);

        $a = $this->todoRepository->findByUuid('a');
        $b = $this->todoRepository->findByUuid('b');
        $c = $this->todoRepository->findByUuid('c');
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertNotNull($c);
        $this->assertLessThan($a->getSortRank(), $c->getSortRank());
        $this->assertLessThan($b->getSortRank(), $a->getSortRank());
    }

    public function testFailedEventInBatchRollsBackPriorMutations(): void
    {
        // Seed an existing item so we can verify it's untouched.
        $this->processor->process([
            new TodoEvent(TodoEvent::TYPE_CREATE, 'seed', $this->checklist->getUuid(), 'seed-content'),
        ], $this->checklist);

        try {
            $this->processor->process([
                new TodoEvent(TodoEvent::TYPE_UPDATE, 'seed', $this->checklist->getUuid(), 'mutated'),
                new TodoEvent(TodoEvent::TYPE_CREATE, 'new-item', $this->checklist->getUuid(), 'fresh'),
                // Should fail and roll back the two changes above.
                new TodoEvent(TodoEvent::TYPE_DELETE, 'no-such-uuid', $this->checklist->getUuid(), null),
            ], $this->checklist);
            $this->fail('Expected InconsistentChecklistEventException');
        } catch (InconsistentChecklistEventException) {
            // Expected.
        }

        // EM was rolled back; clear so we re-read from the DB.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $repo = static::getContainer()->get(TodoRepository::class);

        $seed = $repo->findByUuid('seed');
        $this->assertNotNull($seed);
        $this->assertSame('seed-content', $seed->getContent(), 'seed item must not have been mutated');
        $this->assertNull($repo->findByUuid('new-item'), 'partial create must have been rolled back');
    }
}
