<?php

namespace App\Household\Entity;

use App\Household\HouseholdRankRepository;
use App\RankSort\RankSortableItem;
use App\RankSort\RankSortableList;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * A user's preferred position of a household in their dashboard.
 * Decoupled from HouseholdPrivilege because ordering is a UI preference,
 * not an authorization concept.
 */
#[ORM\Entity(repositoryClass: HouseholdRankRepository::class)]
#[UniqueEntity(fields: ['household', 'user'])]
class HouseholdRank implements RankSortableItem
{
    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: Household::class, inversedBy: "ranks")]
        #[ORM\JoinColumn(name: "household", referencedColumnName: "id", onDelete: "CASCADE")]
        public Household $household,

        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "householdRanks")]
        #[ORM\JoinColumn(name: "`user`", referencedColumnName: "id", onDelete: "CASCADE")]
        public User $user,

        #[ORM\Column(type: "string", nullable: false)]
        private string $sortRank,
    ) {
    }

    public function getUuid(): string
    {
        return $this->user->getId() . ':' . $this->household->getId();
    }

    /**
     * @return non-empty-string
     * @throws \LogicException
     */
    public function getSortRank(): string
    {
        if ('' === $this->sortRank) {
            throw new \LogicException('Sort rank must not be empty. The administrator must rebalance household sorting.');
        }

        return $this->sortRank;
    }

    public function setSortRank(string $sortRank): void
    {
        $this->sortRank = $sortRank;
    }

    public function getList(): RankSortableList
    {
        // User implements RankSortableList<HouseholdRank>, but PHPStan can't
        // resolve that through a plain ManyToOne-typed property into the
        // RankSortableList<static> contract. Same workaround as Checklist::getList.
        /** @phpstan-ignore-next-line */
        return $this->user;
    }
}
