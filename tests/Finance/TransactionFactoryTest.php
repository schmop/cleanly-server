<?php

declare(strict_types=1);

namespace App\Tests\Finance;

use App\Finance\TransactionFactory;
use App\Finance\TransactionType;
use App\Household\Entity\Household;
use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\User\Entity\User;
use App\User\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class TransactionFactoryTest extends TestCase
{
    private UserRepository $userRepository;
    private TransactionFactory $factory;
    /** @var array<int, User> */
    private array $usersById = [];

    protected function setUp(): void
    {
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->userRepository->method('find')->willReturnCallback(
            fn(int $id) => $this->usersById[$id] ?? null,
        );
        $this->factory = new TransactionFactory($this->userRepository);
    }

    public function testValidExpenseTransactionIsBuilt(): void
    {
        $sender = $this->makeUser(1);
        $other = $this->makeUser(2);
        $household = $this->makeHousehold([$sender, $other]);

        $transaction = $this->factory->transactionFromJson(
            $this->payload([
                'sender' => 1,
                'shares' => [
                    ['uuid' => 's1', 'userId' => 1, 'share' => 1],
                    ['uuid' => 's2', 'userId' => 2, 'share' => 2],
                ],
            ]),
            $household,
        );

        $this->assertSame('tx-uuid', $transaction->uuid);
        $this->assertSame(1000, $transaction->amount);
        $this->assertSame(TransactionType::Expense, $transaction->transactionType);
        $this->assertCount(2, $transaction->shares);
    }

    public function testCreatedAtOverrideTakesPriorityOverPayload(): void
    {
        $sender = $this->makeUser(1);
        $household = $this->makeHousehold([$sender]);
        $override = new \DateTimeImmutable('2024-01-01 00:00:00');

        $transaction = $this->factory->transactionFromJson(
            $this->payload(['sender' => 1, 'shares' => [['uuid' => 's', 'userId' => 1, 'share' => 1]]]),
            $household,
            $override,
        );

        $this->assertSame($override->getTimestamp(), $transaction->createdAt->getTimestamp());
    }

    public function testUnknownSenderThrows(): void
    {
        $household = $this->makeHousehold([]);
        $this->expectException(UnexpectedJsonException::class);
        $this->factory->transactionFromJson(
            $this->payload(['sender' => 999, 'shares' => [['uuid' => 's', 'userId' => 999, 'share' => 1]]]),
            $household,
        );
    }

    public function testSenderNotInHouseholdThrows(): void
    {
        $sender = $this->makeUser(1);
        $other = $this->makeUser(2);
        $household = $this->makeHousehold([$other]); // sender isn't a member

        $this->expectException(UnexpectedJsonException::class);
        $this->expectExceptionMessageMatches('/Sender.*not a member/');
        $this->factory->transactionFromJson(
            $this->payload(['sender' => 1, 'shares' => [['uuid' => 's', 'userId' => 2, 'share' => 1]]]),
            $household,
        );
    }

    public function testShareUserNotInHouseholdThrows(): void
    {
        $sender = $this->makeUser(1);
        $stranger = $this->makeUser(2);
        $household = $this->makeHousehold([$sender]); // user 2 isn't a member

        $this->expectException(UnexpectedJsonException::class);
        $this->expectExceptionMessageMatches('/Share user.*not a member/');
        $this->factory->transactionFromJson(
            $this->payload([
                'sender' => 1,
                'shares' => [
                    ['uuid' => 's1', 'userId' => 1, 'share' => 1],
                    ['uuid' => 's2', 'userId' => 2, 'share' => 1],
                ],
            ]),
            $household,
        );
    }

    public function testZeroShareIsRejected(): void
    {
        $sender = $this->makeUser(1);
        $household = $this->makeHousehold([$sender]);

        $this->expectException(UnexpectedJsonException::class);
        $this->expectExceptionMessageMatches('/must be positive/');
        $this->factory->transactionFromJson(
            $this->payload(['sender' => 1, 'shares' => [['uuid' => 's', 'userId' => 1, 'share' => 0]]]),
            $household,
        );
    }

    public function testNegativeShareIsRejected(): void
    {
        $sender = $this->makeUser(1);
        $household = $this->makeHousehold([$sender]);

        $this->expectException(UnexpectedJsonException::class);
        $this->factory->transactionFromJson(
            $this->payload(['sender' => 1, 'shares' => [['uuid' => 's', 'userId' => 1, 'share' => -1]]]),
            $household,
        );
    }

    public function testZeroAmountIsRejected(): void
    {
        $sender = $this->makeUser(1);
        $household = $this->makeHousehold([$sender]);

        $this->expectException(UnexpectedJsonException::class);
        $this->expectExceptionMessageMatches('/amount must be positive/');
        $this->factory->transactionFromJson(
            $this->payload(['sender' => 1, 'amount' => 0, 'shares' => [['uuid' => 's', 'userId' => 1, 'share' => 1]]]),
            $household,
        );
    }

    public function testEmptyShareListIsRejected(): void
    {
        $sender = $this->makeUser(1);
        $household = $this->makeHousehold([$sender]);

        $this->expectException(UnexpectedJsonException::class);
        $this->expectExceptionMessageMatches('/at least one share/');
        $this->factory->transactionFromJson(
            $this->payload(['sender' => 1, 'shares' => []]),
            $household,
        );
    }

    public function testTransferRequiresExactlyOneShare(): void
    {
        $sender = $this->makeUser(1);
        $other = $this->makeUser(2);
        $household = $this->makeHousehold([$sender, $other]);

        $this->expectException(UnexpectedJsonException::class);
        $this->expectExceptionMessageMatches('/transfer money to exactly 1 person/');
        $this->factory->transactionFromJson(
            $this->payload([
                'sender' => 1,
                'type' => 'transfer',
                'shares' => [
                    ['uuid' => 's1', 'userId' => 1, 'share' => 1],
                    ['uuid' => 's2', 'userId' => 2, 'share' => 1],
                ],
            ]),
            $household,
        );
    }

    public function testTransferWithSingleShareIsAccepted(): void
    {
        $sender = $this->makeUser(1);
        $recipient = $this->makeUser(2);
        $household = $this->makeHousehold([$sender, $recipient]);

        $transaction = $this->factory->transactionFromJson(
            $this->payload([
                'sender' => 1,
                'type' => 'transfer',
                'shares' => [['uuid' => 's', 'userId' => 2, 'share' => 1]],
            ]),
            $household,
        );

        $this->assertSame(TransactionType::Transfer, $transaction->transactionType);
        $this->assertCount(1, $transaction->shares);
    }

    public function testInvalidDateStringThrows(): void
    {
        $sender = $this->makeUser(1);
        $household = $this->makeHousehold([$sender]);

        $this->expectException(UnexpectedJsonException::class);
        $this->factory->transactionFromJson(
            $this->payload([
                'sender' => 1,
                'date' => 'not-a-date',
                'shares' => [['uuid' => 's', 'userId' => 1, 'share' => 1]],
            ]),
            $household,
        );
    }

    /**
     * @param array{
     *   sender?: int,
     *   amount?: int,
     *   type?: string,
     *   date?: string,
     *   shares: array<int, array{uuid: string, userId: int, share: int}>,
     * } $overrides
     */
    private function payload(array $overrides): Json
    {
        $tx = array_merge([
            'uuid' => 'tx-uuid',
            'title' => 'lunch',
            'sender' => 1,
            'amount' => 1000,
            'type' => 'expense',
            'date' => '2024-06-15T12:00:00+00:00',
            'createdAt' => '2024-06-15T12:00:00+00:00',
            'shares' => [],
        ], $overrides);

        return new Json(['transaction' => $tx]);
    }

    private function makeUser(int $id): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getName')->willReturn("u$id");
        $this->usersById[$id] = $user;
        return $user;
    }

    /**
     * @param User[] $members
     */
    private function makeHousehold(array $members): Household
    {
        $household = $this->createStub(Household::class);
        $household->method('getMembers')->willReturn(new ArrayCollection($members));
        return $household;
    }
}
