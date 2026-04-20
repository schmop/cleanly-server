<?php

declare(strict_types=1);

namespace App\Tests\Controller\Privilege;

use App\Household\Entity\Household;
use App\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end tests for /api/household/{id}/move.
 *
 * Each user has their own ordering of households; the sortRank lives on
 * HouseholdPrivilege (the per-user pivot). Dashboard returns households ordered
 * by the current user's privilege sortRank.
 */
class HouseholdSortingTest extends WebTestCase
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

    public function testNewHouseholdsAppearInCreationOrderOnDashboard(): void
    {
        $admin = $this->createUser('admin');
        $a = $this->createHousehold($admin);
        $b = $this->createHousehold($admin);
        $c = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        $this->assertDashboardOrder([$a, $b, $c]);
    }

    public function testMoveAfterFirstReordersMiddleToSecond(): void
    {
        $admin = $this->createUser('admin');
        $a = $this->createHousehold($admin);
        $b = $this->createHousehold($admin);
        $c = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        // Start: A, B, C. Move C after A → A, C, B.
        $this->moveHousehold($c, $a);

        $this->assertDashboardOrder([$a, $c, $b]);
    }

    public function testMoveAfterMiddleItemReordersCorrectly(): void
    {
        $admin = $this->createUser('admin');
        $a = $this->createHousehold($admin);
        $b = $this->createHousehold($admin);
        $c = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        // Start: A, B, C. Move A after B → B, A, C.
        $this->moveHousehold($a, $b);

        $this->assertDashboardOrder([$b, $a, $c]);
    }

    public function testMoveWithNullSendsHouseholdToStart(): void
    {
        $admin = $this->createUser('admin');
        $a = $this->createHousehold($admin);
        $b = $this->createHousehold($admin);
        $c = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        // Start: A, B, C. Move C to start (null moveAfterId) → C, A, B.
        $this->moveHousehold($c, null);

        $this->assertDashboardOrder([$c, $a, $b]);
    }

    public function testMoveBottomHouseholdToTopViaNull(): void
    {
        // Reproduces the bug where dragging the bottom household to the top
        // of the dashboard silently fell through to moveAtEnd. With null meaning
        // "move to start", the last item now lands on top.
        $admin = $this->createUser('admin');
        $a = $this->createHousehold($admin);
        $b = $this->createHousehold($admin);
        $c = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        // Start: A, B, C. Drag C (bottom) to position 0 → C, A, B.
        $this->moveHousehold($c, null);
        $this->assertDashboardOrder([$c, $a, $b]);

        // Now drag the new bottom (B) to the top → B, C, A.
        $this->moveHousehold($b, null);
        $this->assertDashboardOrder([$b, $c, $a]);
    }

    public function testMoveAfterLastItemLandsAtEnd(): void
    {
        $admin = $this->createUser('admin');
        $a = $this->createHousehold($admin);
        $b = $this->createHousehold($admin);
        $c = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        // Start: A, B, C. Move A after C (current last) → B, C, A.
        $this->moveHousehold($a, $c);

        $this->assertDashboardOrder([$b, $c, $a]);
    }

    public function testMoveIsIdempotentWhenAlreadyInPosition(): void
    {
        $admin = $this->createUser('admin');
        $a = $this->createHousehold($admin);
        $b = $this->createHousehold($admin);
        $c = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        // B is already after A. Moving B after A must not disturb the order.
        $this->moveHousehold($b, $a);

        $this->assertDashboardOrder([$a, $b, $c]);
    }

    public function testMoveSequenceProducesExpectedFinalOrder(): void
    {
        $admin = $this->createUser('admin');
        $a = $this->createHousehold($admin);
        $b = $this->createHousehold($admin);
        $c = $this->createHousehold($admin);
        $d = $this->createHousehold($admin);
        $this->authenticateAs($admin);

        // Start: A, B, C, D.
        $this->moveHousehold($d, $a); // A, D, B, C
        $this->assertDashboardOrder([$a, $d, $b, $c]);

        $this->moveHousehold($a, $c); // D, B, C, A
        $this->assertDashboardOrder([$d, $b, $c, $a]);

        $this->moveHousehold($b, null); // B, D, C, A
        $this->assertDashboardOrder([$b, $d, $c, $a]);
    }

    public function testReorderIsPerUser(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $a = $this->createHousehold($alice);
        $b = $this->createHousehold($alice);
        $c = $this->createHousehold($alice);
        $a->addMember($bob);
        $b->addMember($bob);
        $c->addMember($bob);
        $this->em->flush();

        $this->authenticateAs($alice);
        $this->moveHousehold($a, $c); // alice's view: B, C, A

        $this->assertDashboardOrder([$b, $c, $a]);

        // Bob's ordering must not be affected.
        $this->authenticateAs($bob);
        $this->assertDashboardOrder([$a, $b, $c]);
    }

    public function testNonMemberCannotMoveHousehold(): void
    {
        $admin = $this->createUser('admin');
        $outsider = $this->createUser('outsider');
        $household = $this->createHousehold($admin);
        $target = $this->createHousehold($admin);
        $this->authenticateAs($outsider);

        $this->client->request(
            'POST',
            "/api/household/{$household->getId()}/move",
            server: $this->authServer('application/json'),
            content: json_encode(['moveAfterId' => $target->getId()], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(403);
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

    private function moveHousehold(Household $household, ?Household $moveAfter): void
    {
        $this->client->request(
            'POST',
            "/api/household/{$household->getId()}/move",
            server: $this->authServer('application/json'),
            content: json_encode(
                ['moveAfterId' => $moveAfter?->getId()],
                JSON_THROW_ON_ERROR,
            ),
        );
        $this->assertResponseIsSuccessful();
    }

    /**
     * @param list<Household> $expected
     */
    private function assertDashboardOrder(array $expected): void
    {
        $this->em->clear();
        $this->client->request('GET', '/api/dashboard', server: $this->authServer());
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string)$this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertArrayHasKey('households', $payload);
        self::assertIsArray($payload['households']);
        $actualIds = array_map(
            static function (mixed $h): int {
                self::assertIsArray($h);
                self::assertArrayHasKey('id', $h);
                self::assertIsInt($h['id']);
                return $h['id'];
            },
            $payload['households'],
        );
        $expectedIds = array_map(
            static function (Household $h): int {
                $id = $h->getId();
                self::assertNotNull($id);
                return $id;
            },
            $expected,
        );
        self::assertSame($expectedIds, $actualIds);
    }
}
