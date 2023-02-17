<?php

namespace App\Command;

use App\Household\HouseholdRepository;
use App\Task\TaskLogRepository;
use App\Task\TaskRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('cleanly:test-get-rotating-assignment')]
readonly class TestGetRotatingAssignmentCommand extends Command
{
    public function __construct(
        private TaskLogRepository   $taskLogRepository,
        private TaskRepository      $taskRepository,
        private HouseholdRepository $householdRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Something to debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $householdId = $input->getArgument('id');
        if (null === $householdId) {
            $output->writeln('No householdId given!');
            return Command::FAILURE;
        }
        $household = $this->householdRepository->find($householdId);
        foreach ($household->getTasks() as $task) {
            dump(
                $task->getId(),
                $task->getName(),
                $this->taskLogRepository->getNextAssignmentRotation(
                    $task,
                ),
            );
        }

        return Command::SUCCESS;
    }
}
