<?php

namespace App\Finance;

use App\Finance\Entity\Transaction;
use App\Finance\Entity\TransactionShare;
use App\Hub\Publisher;
use App\User\Entity\User;
use function Lambdish\Phunctional\filter;

readonly class FinanceTransactionPublisher
{

    public function __construct(private Publisher $publisher)
    {
    }

    public function publish(Transaction $transaction, User $publisher): void
    {
        $this->publishWithType($transaction, $publisher, 'create');
    }

    public function publishDelete(Transaction $transaction, User $publisher): void
    {
        $this->publishWithType($transaction, $publisher, 'delete');
    }

    public function publishUpdate(Transaction $transaction, User $publisher): void
    {
        $this->publishWithType($transaction, $publisher, 'update');
    }

    private function publishWithType(Transaction $transaction, User $publisher, string $type): void
    {
        $users = filter(
            fn(User $user) => $user->getId() !== $publisher->getId(),
            [
                $transaction->sender,
                ...$transaction->shares
                    ->map(fn(TransactionShare $share) => $share->user)
                    ->toArray()
            ],
        );
        $this->publisher->publish(
            $users,
            'finance_transactions',
            [
                'household_id' => $transaction->household->getId(),
                'transaction' => $transaction->jsonSerialize(),
                'type' => $type,
            ]
        );
    }
}
