<?php

namespace App\Finance;

use App\Finance\Entity\Transaction;
use App\Finance\Entity\TransactionShare;
use App\Household\Entity\Household;
use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\User\UserRepository;

readonly class TransactionFactory
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @throws UnexpectedJsonException
     */
    public function transactionFromJson(Json $data, Household $household): Transaction
    {
        $transactionData = $data->json('transaction');
        $sender = $this->userRepository->find($transactionData->int('sender'));
        if ($sender === null) {
            throw new UnexpectedJsonException('Sender with ID ' . $transactionData->string('senderId') . ' not found');
        }

        try {
            $transaction = new Transaction(
                uuid: $transactionData->string('uuid'),
                title: $transactionData->string('title'),
                sender: $sender,
                household: $household,
                amount: $transactionData->int('amount'),
                transactionType: TransactionType::from($transactionData->string('type')),
                date: new \DateTimeImmutable($transactionData->string('date')),
            );
        } catch (\DateMalformedStringException $e) {
            throw new UnexpectedJsonException($e->getMessage(), previous: $e);
        }
        $shares = $transactionData->jsonArray('shares');
        foreach ($shares as $shareData) {
            $share = $this->transactionShareFromJson($shareData, $transaction);
            $transaction->addShare($share);
        }

        $this->assertValidity($transaction, $household);

        return $transaction;
    }

    /**
     * @throws UnexpectedJsonException
     */
    public function transactionShareFromJson(Json $data, Transaction $transaction): TransactionShare
    {
        $user = $this->userRepository->find($data->int('userId'));
        if ($user === null) {
            throw new UnexpectedJsonException('User with ID ' . $data->string('userId') . ' not found');
        }

        return new TransactionShare(
            uuid: $data->string('uuid'),
            user: $user,
            share: $data->int('share'),
            transaction: $transaction,
        );
    }

    /**
     * @throws UnexpectedJsonException
     */
    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new UnexpectedJsonException($message);
        }
    }

    /**
     * @throws UnexpectedJsonException
     */
    private function assertValidity(Transaction $transaction, Household $household): void
    {
        $this->assert(
            $household->getMembers()->contains($transaction->sender),
            sprintf("Sender '%s (id: %s)'is not a member of the household", $transaction->sender->getName(), $transaction->sender->getId())
        );
        foreach ($transaction->shares as $share) {
            $this->assert(
                $household->getMembers()->contains($share->user),
                sprintf("Share user '%s (id: %s)' is not a member of the household", $share->user->getName(), $share->user->getId())
            );
            $this->assert(
                $share->share > 0,
                sprintf("Share (%d) for user '%s (id: %s)' must be positive", $share->share, $share->user->getName(), $share->user->getId())
            );
        }
        $this->assert($transaction->amount > 0, 'Transaction amount must be positive');
        $this->assert(count($transaction->shares) > 0, 'Transaction must have at least one share');
        $totalShares = 0;
        foreach ($transaction->shares as $share) {
            $totalShares += $share->share;
        }
        $this->assert($totalShares > 0, 'Total shares must be positive');
        if ($transaction->transactionType === TransactionType::Transfer) {
            $this->assert($totalShares === 1, 'You can only transfer money to exactly 1 person');
        }
    }
}