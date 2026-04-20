<?php

declare(strict_types=1);

namespace App\Tests\Controller\Privilege;

use App\Finance\TransactionType;
use App\Household\Entity\HouseholdPrivilege;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers Finance controller privilege gates.
 *
 * Voter attributes involved:
 * - ADD_FINANCE_TRANSACTIONS (any member): add / update / delete transaction
 * - READ_HOUSEHOLD_CONTENTS (any member): list transactions, summary
 */
class FinancePrivilegeTest extends WebTestCase
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

    // --- add transaction (ADD_FINANCE_TRANSACTIONS — any member) ---

    public function testRegularMemberCanAddTransaction(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($member);
        $this->client->request(
            'PUT',
            "/api/household/{$household->getId()}/finance/transaction/add",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->transactionPayload($member, $member),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotAddTransaction(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request(
            'PUT',
            "/api/household/{$household->getId()}/finance/transaction/add",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->transactionPayload($admin, $admin),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- delete transaction (ADD_FINANCE_TRANSACTIONS) ---

    public function testSenderMemberCanDeleteRecentTransaction(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $tx = $this->createTransaction($household, $member, new \DateTimeImmutable());

        $this->client->loginUser($member);
        $this->client->request(
            'DELETE',
            "/api/household/{$household->getId()}/finance/transaction/{$tx->uuid}",
        );

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotDeleteTransaction(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $tx = $this->createTransaction($household, $admin, new \DateTimeImmutable());

        $this->client->loginUser($outsider);
        $this->client->request(
            'DELETE',
            "/api/household/{$household->getId()}/finance/transaction/{$tx->uuid}",
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- update transaction (ADD_FINANCE_TRANSACTIONS) ---

    public function testRegularMemberCanUpdateRecentTransaction(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);
        $tx = $this->createTransaction($household, $member, new \DateTimeImmutable());

        $this->client->loginUser($member);
        $this->client->request(
            'PUT',
            "/api/household/{$household->getId()}/finance/transaction/{$tx->uuid}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->transactionPayload($member, $member, $tx->uuid),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotUpdateTransaction(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');
        $tx = $this->createTransaction($household, $admin, new \DateTimeImmutable());

        $this->client->loginUser($outsider);
        $this->client->request(
            'PUT',
            "/api/household/{$household->getId()}/finance/transaction/{$tx->uuid}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->transactionPayload($admin, $admin, $tx->uuid),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- get transactions (READ_HOUSEHOLD_CONTENTS) ---

    public function testRegularMemberCanListTransactions(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($member);
        $this->client->request('GET', "/api/household/{$household->getId()}/finance/transactions");

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotListTransactions(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request('GET', "/api/household/{$household->getId()}/finance/transactions");

        $this->assertResponseStatusCodeSame(403);
    }

    // --- finance summary (READ_HOUSEHOLD_CONTENTS) ---

    public function testRegularMemberCanReadFinanceSummary(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $member = $this->addMember($household, HouseholdPrivilege::PRIVILEGE_USER);

        $this->client->loginUser($member);
        $this->client->request('GET', "/api/household/{$household->getId()}/finance/summary");

        $this->assertResponseIsSuccessful();
    }

    public function testNonMemberCannotReadFinanceSummary(): void
    {
        $admin = $this->createUser('admin');
        $household = $this->createHousehold($admin);
        $outsider = $this->createUser('outsider');

        $this->client->loginUser($outsider);
        $this->client->request('GET', "/api/household/{$household->getId()}/finance/summary");

        $this->assertResponseStatusCodeSame(403);
    }

    private function transactionPayload(User $sender, User $shareUser, ?string $uuid = null): string
    {
        $now = new \DateTimeImmutable();
        return json_encode([
            'transaction' => [
                'uuid' => $uuid ?? uniqid('tx_', true),
                'title' => 'Groceries',
                'sender' => $sender->getId(),
                'amount' => 1000,
                'type' => TransactionType::Expense->value,
                'date' => $now->format(DATE_ATOM),
                'createdAt' => $now->format(DATE_ATOM),
                'shares' => [[
                    'uuid' => uniqid('ts_', true),
                    'userId' => $shareUser->getId(),
                    'share' => 1,
                ]],
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
