<?php

declare(strict_types=1);

namespace App\Tests\Controller\Privilege;

use App\Todo\Entity\Checklist;
use App\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end tests for todo reordering within a checklist via the
 * /api/household/checklist/{uuid}/update event stream. Todo reordering
 * uses the insertBefore convention: data=<uuid> → place the moved todo
 * before that uuid; data=null → no successor → place at the end.
 */
class TodoSortingTest extends WebTestCase
{
    use PrivilegeFixtureTrait;

    private KernelBrowser $client;
    private JWTTokenManagerInterface $jwtManager;
    private string $authHeader = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->initFixtures();
        $this->jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupFixtures();
        } finally {
            parent::tearDown();
        }
    }

    public function testDraggingTopTodoToEndViaNullPlacesItAtEnd(): void
    {
        // Reproduces the bug where dragging a todo to the bottom of the list
        // silently landed it at the top because moveBefore(null) used to mean
        // "move to start". The drag-to-end drop sends insertBefore=null.
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $checklist = $this->createChecklist($household);
        $this->authenticateAs($admin);

        $this->sendEvents($checklist, [
            $this->createEvent($checklist, 'a', 'Apple'),
            $this->createEvent($checklist, 'b', 'Banana'),
            $this->createEvent($checklist, 'c', 'Cherry'),
        ]);

        // Start: a, b, c. Drag a to the bottom → null insertBefore → b, c, a.
        $this->sendEvents($checklist, [
            $this->sortEvent($checklist, 'a', null),
        ]);

        $this->assertTodoOrder($checklist, ['b', 'c', 'a']);
    }

    public function testMoveTodoBeforeExistingTodo(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $checklist = $this->createChecklist($household);
        $this->authenticateAs($admin);

        $this->sendEvents($checklist, [
            $this->createEvent($checklist, 'a', 'Apple'),
            $this->createEvent($checklist, 'b', 'Banana'),
            $this->createEvent($checklist, 'c', 'Cherry'),
        ]);

        // Move c before b → a, c, b.
        $this->sendEvents($checklist, [
            $this->sortEvent($checklist, 'c', 'b'),
        ]);

        $this->assertTodoOrder($checklist, ['a', 'c', 'b']);
    }

    public function testMoveTodoToStartByInsertingBeforeFirst(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $checklist = $this->createChecklist($household);
        $this->authenticateAs($admin);

        $this->sendEvents($checklist, [
            $this->createEvent($checklist, 'a', 'Apple'),
            $this->createEvent($checklist, 'b', 'Banana'),
            $this->createEvent($checklist, 'c', 'Cherry'),
        ]);

        // Move c before a → c, a, b.
        $this->sendEvents($checklist, [
            $this->sortEvent($checklist, 'c', 'a'),
        ]);

        $this->assertTodoOrder($checklist, ['c', 'a', 'b']);
    }

    private function authenticateAs(User $user): void
    {
        $this->authHeader = 'Bearer ' . $this->jwtManager->create($user);
    }

    /**
     * @param list<array{type: string, uuid: string, checklistUuid: string, data: string|null}> $events
     */
    private function sendEvents(Checklist $checklist, array $events): void
    {
        $this->client->request(
            'POST',
            "/api/household/checklist/{$checklist->getUuid()}/update",
            server: [
                'HTTP_AUTHORIZATION' => $this->authHeader,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['events' => $events], JSON_THROW_ON_ERROR),
        );
        $this->assertResponseIsSuccessful();
    }

    /**
     * @return array{type: string, uuid: string, checklistUuid: string, data: string|null}
     */
    private function createEvent(Checklist $checklist, string $uuid, string $content): array
    {
        return [
            'type' => 'create',
            'uuid' => $uuid,
            'checklistUuid' => $checklist->getUuid(),
            'data' => $content,
        ];
    }

    /**
     * @return array{type: string, uuid: string, checklistUuid: string, data: string|null}
     */
    private function sortEvent(Checklist $checklist, string $uuid, ?string $insertBeforeUuid): array
    {
        return [
            'type' => 'sort',
            'uuid' => $uuid,
            'checklistUuid' => $checklist->getUuid(),
            'data' => $insertBeforeUuid,
        ];
    }

    /**
     * @param list<string> $expectedUuids
     */
    private function assertTodoOrder(Checklist $checklist, array $expectedUuids): void
    {
        $this->em->clear();
        $reloaded = $this->em->find(Checklist::class, $checklist->getUuid());
        self::assertNotNull($reloaded);
        $actualUuids = array_map(
            static fn ($todo) => $todo->getUuid(),
            $reloaded->getChecklist()->toArray(),
        );
        self::assertSame($expectedUuids, array_values($actualUuids));
    }
}
