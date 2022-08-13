<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Push\DeviceRepository;
use App\Task\TaskRepository;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('cleanly:test-get-devices-by-task')]
class TestGetDevicesByTaskCommand extends Command
{
    public function __construct(
        private readonly DeviceRepository $deviceRepository,
        private readonly TaskRepository $taskRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addArgument('id', InputArgument::REQUIRED, 'Something to debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $task = $input->getArgument('id');
        if (null === $task) {
            $output->writeln('No taskId given!');
            return Command::FAILURE;
        }
        dump(
            $this->deviceRepository->findByHousehold(
                $this->taskRepository->findOneBy(['id' => $task])?->getHousehold()
            )
        );
        return Command::SUCCESS;
    }
}
