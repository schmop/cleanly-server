<?php

declare(strict_types=1);

namespace App\Tests\Controller\Privilege;

use App\Household\Entity\HouseholdPrivilege;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers StarController privilege gate.
 *
 * Voter attribute: READ_HOUSEHOLD_CONTENTS (any member).
 * Expected denial response: HTTP 403.
 */
class StarPrivilegeTest extends WebTestCase
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

    public function testRegularMemberCanReadStars(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($member);
        $this->client->request('GET', "/api/household/{$household->getId()}/stars");

        $this->assertResponseIsSuccessful();
    }

    public function testAdminCanReadStars(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);

        $this->client->loginUser($admin);
        $this->client->request('GET', "/api/household/{$household->getId()}/stars");

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotReadStars(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request('GET', "/api/household/{$household->getId()}/stars");

        $this->assertResponseStatusCodeSame(403);
    }
}
