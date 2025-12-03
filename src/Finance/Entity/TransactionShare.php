<?php

namespace App\Finance\Entity;

use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(/*repositoryClass: TodoRepository::class*/)]
readonly class TransactionShare implements \JsonSerializable
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: "string")]
        public string      $uuid,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
        public User        $user,

        #[ORM\Column(type: "integer", nullable: false)]
        public int         $share, // parts

        #[ORM\ManyToOne(targetEntity: Transaction::class, inversedBy: "shares")]
        #[ORM\JoinColumn(name: "transaction_uuid", referencedColumnName: "uuid", nullable: false, onDelete: "CASCADE")]
        public Transaction $transaction,
    ) {
    }

    /**
     * @return array{
     *   'uuid': string,
     *   'userId': int,
     *   'share': int,
     * }
     */
    public function jsonSerialize(): array
    {
        $userId = $this->user->getId();
        if ($userId === null) {
            throw new \LogicException('User ID is null, cannot serialize TransactionShare');
        }
        return [
            'uuid' => $this->uuid,
            'userId' => $userId,
            'share' => $this->share,
        ];
    }
}
