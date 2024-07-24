<?php

namespace App\Command;

use AlexCrawford\LexoRank\Rank;
use App\Household\HouseholdRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('cleanly:rebalance-checklist-ordering')]
class RebalanceChecklistOrdering extends Command
{
    public function __construct(
        private readonly HouseholdRepository $householdRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $households = $this->householdRepository->findAll();
        foreach ($households as $household) {
            $checklists = $household->getChecklists();
            if ($checklists->isEmpty()) {
                $output->writeln('No checklists found for household ' . $household->getName());
                continue;
            }
            $midIndex = (int) floor($checklists->count() / 2);
            $midRank = Rank::forEmptySequence();
            $checklists->get($midIndex)?->setSortRank($midRank->get());
            $currentRank = $midRank;
            for ($i = $midIndex + 1; $i < $checklists->count(); $i++) {
                $currentRank = Rank::after($currentRank);
                $checklist = $checklists->get($i);
                if (null === $checklist) {
                    $output->writeln(sprintf(
                        'Checklist at index %d (size %d) is null for household "%s".',
                        $i,
                        $checklists->count(),
                        $household->getName(),
                    ));
                    continue;
                }
                $checklist->setSortRank($currentRank->get());
            }
            $currentRank = $midRank;
            for ($i = $midIndex - 1; $i >= 0; $i--) {
                $currentRank = Rank::before($currentRank);
                $checklist = $checklists->get($i);
                if (null === $checklist) {
                    $output->writeln(sprintf(
                        'Checklist at index %d (size %d) is null for household "%s".',
                        $i,
                        $checklists->count(),
                        $household->getName(),
                    ));
                    continue;
                }
                $checklist->setSortRank($currentRank->get());
            }
            $output->writeln(sprintf(
                'Rebalanced %d checklists for household "%s".',
                $checklists->count(),
                $household->getName(),
            ));
            $this->householdRepository->save($household);
        }

        return Command::SUCCESS;
    }
}
