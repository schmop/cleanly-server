<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdInvite;
use App\Tests\Controller\Privilege\PrivilegeFixtureTrait;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Behaviour of `GET /api/household/join-by-token/{token}`. The invite's primary
 * key is `token`, not `id`, so the route depends on #[MapEntity(id: 'token')]
 * to resolve it. Without that hint the argument cannot be resolved at all and
 * the endpoint fails before the controller body runs.
 */
class HouseholdJoinByTokenTest extends WebTestCase
{
    use PrivilegeFixtureTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->initFixtures();
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupFixtures();
        } finally {
            parent::tearDown();
        }
    }

    public function testValidTokenAddsInviteeToHouseholdAndConsumesTheInvite(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $invitee = $this->createUser('invitee');
        $invite = $this->createInvite($household, $invitee, $admin);
        $token = $invite->getToken();

        $this->client->loginUser($invitee);
        $this->client->request('GET', "/api/household/join-by-token/$token");

        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $this->assertTrue($this->isMember($household, $invitee), 'invitee should have joined the household');
        $this->assertNull(
            $this->em->find(HouseholdInvite::class, $token),
            'the invite should be consumed once redeemed',
        );
    }

    public function testUnknownTokenIsNotFound(): void
    {
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request('GET', '/api/household/join-by-token/definitely-not-a-real-token');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testOutdatedInviteIsRejected(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $invitee = $this->createUser('invitee');
        $invite = $this->createInvite($household, $invitee, $admin);
        $this->expireInvite($invite);

        $this->client->loginUser($invitee);
        $this->client->request('GET', "/api/household/join-by-token/{$invite->getToken()}");

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('Outdated invite!', (string)$this->client->getResponse()->getContent());

        $this->em->clear();
        $this->assertFalse($this->isMember($household, $invitee), 'an expired invite must not add the member');
    }

    public function testExistingMemberCannotJoinTwice(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $invite = $this->createInvite($household, $admin, $admin);

        $this->client->loginUser($admin);
        $this->client->request('GET', "/api/household/join-by-token/{$invite->getToken()}");

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString(
            'Already member of this household!',
            (string)$this->client->getResponse()->getContent(),
        );
    }

    // -- helpers --

    private function createInvite(Household $household, ?User $invitee, ?User $inviter): HouseholdInvite
    {
        $invite = new HouseholdInvite(uniqid('invite_', true), $household, $invitee, $inviter);
        $this->em->persist($invite);
        $this->em->flush();

        return $invite;
    }

    /** No setter for validUntil, so push it into the past directly. */
    private function expireInvite(HouseholdInvite $invite): void
    {
        $table = $this->em->getClassMetadata(HouseholdInvite::class)->getTableName();
        $this->em->getConnection()->executeStatement(
            "UPDATE $table SET valid_until = :past WHERE token = :token",
            ['past' => '2020-01-01 00:00:00', 'token' => $invite->getToken()],
        );
        $this->em->clear();
    }

    private function isMember(Household $household, User $user): bool
    {
        $reloaded = $this->em->find(Household::class, $household->getId());
        $this->assertNotNull($reloaded);

        return $reloaded->getMembers()->exists(
            fn(int $i, User $member): bool => $member->getId() === $user->getId(),
        );
    }
}
