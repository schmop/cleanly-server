<?php

namespace App\Finance;

readonly class Debt implements \JsonSerializable
{
    public function __construct(
        public int $creditorId, // the one who will receive money
        public int $debtorId, // the one who owes money
        public int $amount, // in cents
    ) {
    }

    /**
     * @return array{
     *     'fromUserId': int,
     *     'toUserId': int,
     *     'amount': int,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'fromUserId' => $this->debtorId,
            'toUserId' => $this->creditorId,
            'amount' => $this->amount,
        ];
    }
}