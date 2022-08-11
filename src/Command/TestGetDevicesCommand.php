<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Household\HouseholdRepository;
use App\Push\DeviceRepository;

#[AsCommand('cleanly:test-get-devices-by-household')]
class TestGetDevicesCommand extends Command
{
    public function __construct(
        private readonly DeviceRepository $deviceRepository,
        private readonly HouseholdRepository $householdRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $householdId = $input->getFirstArgument();
        if (null === $householdId) {
            $output->writeln('No householdId given!');
            return Command::FAILURE;
        }
        dump(
            $this->deviceRepository->findByHousehold(
                $this->householdRepository->findOneBy(['id' => $householdId])
            )
        );
        return Command::SUCCESS;
    }
}
