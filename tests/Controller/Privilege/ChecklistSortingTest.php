<?php

declare(strict_types=1);

namespace App\Tests\Controller\Privilege;

use App\Household\Entity\Household;
use App\Todo\Entity\Checklist;
use App\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end tests for the /move endpoint and the underlying sortRank ordering.
 *
 * Checklists are ordered by sortRank ASC on the Household relation. New checklists
 * are appended via ChecklistFactory (Rank::after the last item). The /move endpoint
 * supports moveAfter(uuid|null); null means "move to end".
 *
 * The api firewall is stateless + JWT, so we authenticate each request with a real
 * JWT token rather than relying on KernelBrowser::loginUser (which only survives a
 * single request on stateless firewalls).
 */
class ChecklistSortingTest extends WebTestCase
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

    public function testNewChecklistsAreAppendedInCreationOrder(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        $a = $this->addChecklistViaApi($household);
        $b = $this->addChecklistViaApi($household);
        $c = $this->addChecklistViaApi($household);

        $this->assertSortOrder($household, [$a, $b, $c]);
    }

    public function testMoveAfterFirstPlacesChecklistBetweenFirstAndSecond(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        $a = $this->addChecklistViaApi($household);
        $b = $this->addChecklistViaApi($household);
        $c = $this->addChecklistViaApi($household);

        // Start: A, B, C. Move C after A → A, C, B.
        $this->moveChecklist($c, $a);

        $this->assertSortOrder($household, [$a, $c, $b]);
    }

    public function testMoveAfterMiddleItemReordersCorrectly(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        $a = $this->addChecklistViaApi($household);
        $b = $this->addChecklistViaApi($household);
        $c = $this->addChecklistViaApi($household);

        // Start: A, B, C. Move A after B → B, A, C.
        $this->moveChecklist($a, $b);

        $this->assertSortOrder($household, [$b, $a, $c]);
    }

    public function testMoveWithNullUuidSendsChecklistToEnd(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        $a = $this->addChecklistViaApi($household);
        $b = $this->addChecklistViaApi($household);
        $c = $this->addChecklistViaApi($household);

        // Start: A, B, C. Move A to end → B, C, A.
        $this->moveChecklist($a, null);

        $this->assertSortOrder($household, [$b, $c, $a]);
    }

    public function testMoveAfterLastItemKeepsItAtEnd(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        $a = $this->addChecklistViaApi($household);
        $b = $this->addChecklistViaApi($household);
        $c = $this->addChecklistViaApi($household);

        // Start: A, B, C. Move A after C → B, C, A.
        $this->moveChecklist($a, $c);

        $this->assertSortOrder($household, [$b, $c, $a]);
    }

    public function testMoveIsIdempotentWhenAlreadyInPosition(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        $a = $this->addChecklistViaApi($household);
        $b = $this->addChecklistViaApi($household);
        $c = $this->addChecklistViaApi($household);

        // B is already after A. Moving B after A must not disturb the order.
        $this->moveChecklist($b, $a);

        $this->assertSortOrder($household, [$a, $b, $c]);
    }

    public function testMoveSequenceProducesExpectedFinalOrder(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        $a = $this->addChecklistViaApi($household);
        $b = $this->addChecklistViaApi($household);
        $c = $this->addChecklistViaApi($household);
        $d = $this->addChecklistViaApi($household);

        // Start: A, B, C, D.
        $this->moveChecklist($d, $a); // A, D, B, C
        $this->assertSortOrder($household, [$a, $d, $b, $c]);

        $this->moveChecklist($a, $c); // D, B, C, A
        $this->assertSortOrder($household, [$d, $b, $c, $a]);

        $this->moveChecklist($b, null); // D, C, A, B
        $this->assertSortOrder($household, [$d, $c, $a, $b]);
    }

    private function authenticateAs(User $user): void
    {
        $this->authHeader = 'Bearer ' . $this->jwtManager->create($user);
    }

    /**
     * @return array<string, string>
     */
    private function authServer(string $contentType = ''): array
    {
        $headers = ['HTTP_AUTHORIZATION' => $this->authHeader];
        if ($contentType !== '') {
            $headers['CONTENT_TYPE'] = $contentType;
        }

        return $headers;
    }

    private function addChecklistViaApi(Household $household): Checklist
    {
        $this->client->request(
            'PUT',
            "/api/household/{$household->getId()}/checklist/add",
            server: $this->authServer(),
        );
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string)$this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertArrayHasKey('uuid', $payload);
        self::assertIsString($payload['uuid']);
        $checklist = $this->em->find(Checklist::class, $payload['uuid']);
        self::assertNotNull($checklist);

        return $checklist;
    }

    private function moveChecklist(Checklist $checklist, ?Checklist $moveAfter): void
    {
        $this->client->request(
            'POST',
            "/api/household/checklist/{$checklist->getUuid()}/move",
            server: $this->authServer('application/json'),
            content: json_encode(
                ['moveAfterUuid' => $moveAfter?->getUuid()],
                JSON_THROW_ON_ERROR,
            ),
        );
        $this->assertResponseIsSuccessful();
    }

    /**
     * @param list<Checklist> $expected
     */
    private function assertSortOrder(Household $household, array $expected): void
    {
        $this->em->clear();
        $reloaded = $this->em->find(Household::class, $household->getId());
        self::assertNotNull($reloaded);
        $actualUuids = array_map(
            static fn (Checklist $c): string => $c->getUuid(),
            $reloaded->getChecklists()->toArray(),
        );
        $expectedUuids = array_map(static fn (Checklist $c): string => $c->getUuid(), $expected);
        self::assertSame($expectedUuids, array_values($actualUuids));
    }
}
