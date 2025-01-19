<?php

namespace App\Command;

use App\Push\DeviceRepository;
use App\Push\Entity\Device;
use App\Push\Pusher;
use App\Utils\UuidGenerator;
use Kreait\Firebase\Contract\Messaging;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use function Lambdish\Phunctional\map;

#[AsCommand('cleanly:test-send-push')]
class TestSendPushNotificationCommand extends Command
{
    public function __construct(
        private readonly DeviceRepository $deviceRepository,
        private readonly Messaging $messaging,
        private readonly UuidGenerator $uuidGenerator,
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
            $output->writeln('No userid given!');
            return Command::FAILURE;
        }
        $logger = new ConsoleLogger($output, [
            LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::DEBUG => OutputInterface::VERBOSITY_NORMAL,
        ]);
        $pusher = new Pusher($this->messaging, $this->deviceRepository, $logger);
        $uuid = $this->uuidGenerator->v4();
        $logger->info("Sending push noti: $uuid");
        $pusher->publishToDevices($this->deviceRepository->findBy(['user' => $userId]), "Testnoti $uuid", "I am a test notification");

        return Command::SUCCESS;
    }
}
