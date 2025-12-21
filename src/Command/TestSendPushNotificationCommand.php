<?php

namespace App\Command;

use App\Push\DeviceRepository;
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

#[AsCommand('cleanly:test-send-push')]
class TestSendPushNotificationCommand extends Command
{
    public function __construct(
        private readonly Pusher $pusher,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addArgument('id', InputArgument::REQUIRED, 'Something to debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('id');
        if (!is_string($userId)) {
            $output->writeln('No userid given!');
            return Command::FAILURE;
        }
        $userId = intval($userId);
        $this->pusher->publishTestNotification($userId);

        return Command::SUCCESS;
    }
}
