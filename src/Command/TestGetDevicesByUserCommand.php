<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Household\HouseholdRepository;
use App\Push\DeviceRepository;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('cleanly:test-get-devices-by-user')]
class TestGetDevicesByUserCommand extends Command
{
    public function __construct(
        private readonly DeviceRepository $deviceRepository,
        private readonly HouseholdRepository $householdRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addArgument('id', InputArgument::REQUIRED, 'Something to debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('id');
        if (null === $userId) {
            $output->writeln('No userId given!');
            return Command::FAILURE;
        }
        dump(
            $this->deviceRepository->findBy([
                'user_id' => $userId
            ])
        );
        return Command::SUCCESS;
    }
}
