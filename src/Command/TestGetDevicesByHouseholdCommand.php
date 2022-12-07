<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Household\HouseholdRepository;
use App\Push\DeviceRepository;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('cleanly:test-get-devices-by-household')]
class TestGetDevicesByHouseholdCommand extends Command
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
        $householdId = $input->getArgument('id');
        if (null === $householdId) {
            $output->writeln('No householdId given!');
            return Command::FAILURE;
        }
        $household = $this->householdRepository->findOneBy(['id' => $householdId]);
        if (null !== $household) {
            dump(
                $this->deviceRepository->findByHousehold($household)
            );
        }

        return Command::SUCCESS;
    }
}
