<?php

namespace App\Finance\Entity;

use App\Finance\TransactionRepository;
use App\Finance\TransactionType;
use App\Household\Entity\Household;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction implements \JsonSerializable
{


    /**
     * @var Collection<int, TransactionShare>
     */
    #[ORM\OneToMany(targetEntity: TransactionShare::class, mappedBy: "transaction", cascade: ["all"], orphanRemoval: true)]
    public Collection $shares;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: "string")]
        public readonly string $uuid,

        #[ORM\Column(type: "string", nullable: false)]
        public readonly string $title,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
        public readonly User $sender,

        #[ORM\ManyToOne(targetEntity: Household::class)]
        #[ORM\JoinColumn(name: "household_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
        public readonly Household $household,

        #[ORM\Column(type: "integer", nullable: false)]
        public readonly int $amount, // in cents

        #[ORM\Column(type: "string", nullable: false, enumType: TransactionType::class, options: ['default' => 'expense'])]
        public readonly TransactionType $transactionType,

        #[ORM\Column(type: "datetime_immutable", nullable: false)]
        public readonly \DateTimeImmutable $date,

        #[ORM\Column(type: "datetime_immutable", nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
        public readonly \DateTimeImmutable $createdAt,
    ) {
        $this->shares = new ArrayCollection();
    }

    /**
     * @return array{
     *   'uuid': string,
     *   'title': string,
     *   'sender': int,
     *   'amount': int,
     *   'type': string,
     *   'date': string,
     *   'createdAt': string,
     *   'shares': array<array{
     *     'uuid': string,
     *     'userId': int,
     *     'share': int,
     *   }>,
     * }
     */
    public function jsonSerialize(): array
    {
        $senderId = $this->sender->getId();
        if ($senderId === null) {
            throw new \LogicException('Sender ID is null, cannot serialize Transaction');
        }
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'sender' => $senderId,
            'amount' => $this->amount,
            'type' => $this->transactionType->value,
            'date' => $this->date->format(DATE_ATOM),
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'shares' => array_map(
                fn(TransactionShare $share) => $share->jsonSerialize(),
                $this->shares->toArray()
            ),
        ];
    }
    public function addShare(TransactionShare $share): void
    {
        $this->shares->add($share);
    }

}
