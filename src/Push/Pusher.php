<?php

namespace App\Push;

use App\Finance\Entity\Transaction;
use App\Finance\Entity\TransactionShare;
use App\Finance\TransactionType;
use App\Household\Entity\Household;
use App\Push\Entity\Device;
use App\Task\Entity\Task;
use App\Todo\Entity\Checklist;
use App\User\Entity\User;
use App\Utils\UuidGenerator;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\SendReport;
use Psr\Log\LoggerInterface;
use function Lambdish\Phunctional\filter;
use function Lambdish\Phunctional\map;

readonly class Pusher
{

    public function __construct(
        private Messaging        $messaging,
        private DeviceRepository $deviceRepository,
        private LoggerInterface  $logger,
        private UuidGenerator    $uuidGenerator,
    ) {
    }

    public function publishChecklistUpdate(User $exclude, Checklist $checklist): void
    {
        $household = $checklist->getHousehold();
        $devices = filter(
            fn(Device $device) => $device->getUser() !== $exclude && $checklist->getSubscribers()->contains($device->getUser()),
            $this->deviceRepository->findByHousehold($household),
        );
        $this->publishToDevices(
            $devices,
            'Neuigkeiten in Checkliste!',
            sprintf('Checkliste "%s" in "%s" wurde aktualisiert!', $checklist->getName(), $household->getName()),
        );
    }

    public function publishTaskDone(Task $task, User $exclude): void
    {
        $devices = filter(
            fn(Device $device) => $device->getUser() !== $exclude && $device->getUser()->getUserSettings()->notifyTaskDone === true,
            $this->deviceRepository->findByHousehold($task->getHousehold()),
        );
        $this->publishToDevices(
            $devices,
            sprintf('%s wurde erledigt!', $task->getName()),
            sprintf(
                '%s hat in %s gerade %s erledigt!',
                $exclude->getName(),
                $task->getHousehold()->getName(),
                $task->getName(),
            ),
        );
    }

    public function publishTaskDue(Task $task): void
    {
        $household = $task->getHousehold();
        $devices = filter(
            fn(Device $device) => $device->getUser()->getUserSettings()->notifyTaskDue === true,
            $this->deviceRepository->findByHousehold($household),
        );
        $this->publishToDevices(
            $devices,
            'Aufgabe wird dringend!',
            sprintf(
                '%s sollte in %s bald erledigt werden!',
                $task->getName(),
                $household->getName(),
            ),
        );
    }

    /**
     * @param User $messenger The user who initiated the transaction
     */
    public function publishNewFinanceTransaction(Transaction $transaction, User $messenger): void
    {
        foreach ($transaction->shares as $share) {
            if ($share->user->getId() === $messenger->getId()) {
                continue;
            }
            if ($share->user === $transaction->sender) {
                continue;
            }
            $this->publishFinanceTransactionForShare($share);
        }
        if ($transaction->sender->getId() !== $messenger->getId()) {
            $this->publishFinanceTransactionSender($transaction, $messenger);
        }
    }

    private function publishFinanceTransactionForShare(TransactionShare $share): void
    {
        $transaction = $share->transaction;
        $shareFormatted = sprintf("%s €", number_format($transaction->amount * $share->share / 100, 2));
        $this->publishToDevices(
            devices: filter(
                fn(Device $device) => $device->getUser()->getUserSettings()->notifyNewTransactions === true,
                $this->deviceRepository->findByUser($share->user),
            ),
            title: match ($transaction->transactionType) {
                TransactionType::Expense => sprintf('Neue Ausgabe in %s', $transaction->household->getName()),
                TransactionType::Income => 'Neue Einnahme in ' . $transaction->household->getName(),
                TransactionType::Transfer => 'Neue Überweisung in ' . $transaction->household->getName(),
            },
            content: match ($transaction->transactionType) {
                TransactionType::Expense => sprintf('%s hat %s bezahlt, dein Anteil ist %s.', $transaction->sender->getName(), $transaction->title, $shareFormatted),
                TransactionType::Income => sprintf('%s hat für %s Geld erhalten, dein Anteil ist %s.', $transaction->sender->getName(), $transaction->title, $shareFormatted),
                TransactionType::Transfer => sprintf('%s hat dir %s für %s gegeben.', $transaction->sender->getName(), $shareFormatted, $transaction->title),
            },
        );
    }

    private function publishFinanceTransactionSender(Transaction $transaction, User $messenger): void
    {
        $shareFormatted = sprintf("%s €", number_format($transaction->amount / 100, 2));
        $this->publishToDevices(
            devices: filter(
                fn(Device $device) => $device->getUser()->getUserSettings()->notifyNewTransactions === true,
                $this->deviceRepository->findByUser($transaction->sender),
            ),
            title: match ($transaction->transactionType) {
                TransactionType::Expense => sprintf('Ausgabe in %s erstellt', $transaction->household->getName()),
                TransactionType::Income => sprintf('Einnahme in %s erstellt', $transaction->household->getName()),
                TransactionType::Transfer => sprintf('Überweisung in %s erstellt', $transaction->household->getName()),
            },
            content: match ($transaction->transactionType) {
                TransactionType::Expense => sprintf('%s hat deine Ausgabe %s über %s erstellt.', $messenger->getName(), $transaction->title, $shareFormatted),
                TransactionType::Income => sprintf('%s hat deine Einnahme %s über %s erstellt.', $messenger->getName(), $transaction->title, $shareFormatted),
                TransactionType::Transfer => sprintf('%s hat deine Überweisung über %s für %s erstellt.', $messenger->getName(), $shareFormatted, $transaction->title),
            },
        );
    }

    /**
     * @param User[] $invitees
     */
    public function publishInvites(User $inviter, array $invitees, Household $household): void
    {
        $devices = filter(
            fn(Device $device) => $device->getUser()->getUserSettings()->notifyInvites === true,
            $this->deviceRepository->findByUsers($invitees),
        );
        $this->publishToDevices(
            $devices,
            "Einladung in Haushalt",
            sprintf(
                "Einladung von %s in den Haushalt %s erhalten",
                $inviter->getName(),
                $household->getName(),
            ),
        );
    }

    public function publishTaskAssign(Task $task, User $assignee): void
    {
        $this->publishToDevices(
            $this->deviceRepository->findByUser($assignee),
            'Aufgabe zugewiesen',
            sprintf(
                'Dir wurde %s in %s zugewiesen!',
                $task->getName(),
                $task->getHousehold()->getName(),
            ),
        );
    }

    public function publishTestNotification(int $receiverId): void
    {
        $uuid = $this->uuidGenerator->v4();
        $this->logger->info("Sending push noti: $uuid");
        $this->publishToDevices($this->deviceRepository->findBy(['user' => $receiverId]), "Testnoti $uuid", "I am a test notification");
    }

    /**
     * @param Device[] $devices
     */
    private function publishToDevices(array $devices, string $title, string $content, ?string $imageUrl = null): void
    {
        if (empty($devices)) {
            return;
        }
        $deviceIds = array_values(array_map(fn(Device $device) => $device->getPushId(), $devices));
        try {
            $message = CloudMessage::new()->withNotification(Notification::create($title, $content, $imageUrl));
            $report = $this->messaging->sendMulticast($message, $deviceIds);
            $this->logger->info('Push notification sent to {count} devices, {success} successful, {failed} failed!', [
                'count' => count($deviceIds),
                'success' => $report->successes()->count(),
                'failed' => $report->failures()->count(),
                'failures' => map(
                    fn(SendReport $report) => [
                        'message' => $report->error(),
                        'exception' => $report->error(),
                    ],
                    $report->failures()->getItems(),
                ),
            ]);
        } catch (MessagingException|FirebaseException $e) {
            $this->logger->error('Could not send push notifications, reason: {message}!', [
                'message' => $e->getMessage(),
                'exception' => $e
            ]);
        }
    }
}
