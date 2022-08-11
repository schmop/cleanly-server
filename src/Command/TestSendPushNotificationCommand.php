<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Household\HouseholdRepository;
use App\Push\DeviceRepository;
use App\Push\Pusher;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('cleanly:test-send-push')]
class TestSendPushNotificationCommand extends Command
{
    public function __construct(
        private readonly DeviceRepository $deviceRepository,
        private readonly Messaging $messaging,
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
        $devices = $this->deviceRepository->findBy(['user_id' => $userId]);
        $message = CloudMessage::new ()->withNotification(Notification::create("Testnotification", "I am a test notification", ));
        $this->messaging->sendMulticast($message, $devices);

        return Command::SUCCESS;
    }
}
