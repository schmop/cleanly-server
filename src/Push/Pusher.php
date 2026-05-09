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
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
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
            data: [
                'type' => 'checklist_update',
                'householdId' => (string) $household->getId(),
                'checklistUuid' => $checklist->getUuid(),
            ],
            groupKey: sprintf('checklist_update_%d', $household->getId()),
            groupSummary: $household->getName(),
        );
    }

    /**
     * @param User $completer The user credited with completing the task. Their
     *        name appears in the notification body and they are excluded from
     *        the recipient list (the system already attributes the action to them).
     * @param User|null $clicker The user who triggered the request, when different
     *        from the completer (e.g. a moderator marking done on someone else's
     *        behalf). Also excluded from the recipient list so they don't receive
     *        a push for an action they just performed.
     */
    public function publishTaskDone(Task $task, User $completer, ?User $clicker = null): void
    {
        $devices = filter(
            fn(Device $device) =>
                $device->getUser() !== $completer
                && $device->getUser() !== $clicker
                && $device->getUser()->getUserSettings()->notifyTaskDone === true,
            $this->deviceRepository->findByHousehold($task->getHousehold()),
        );
        $this->publishToDevices(
            $devices,
            sprintf('%s wurde erledigt!', $task->getName()),
            sprintf(
                '%s hat in %s gerade %s erledigt!',
                $completer->getName(),
                $task->getHousehold()->getName(),
                $task->getName(),
            ),
            data: [
                'type' => 'task_done',
                'householdId' => (string) $task->getHousehold()->getId(),
                'taskId' => (string) $task->getId(),
            ],
            groupKey: sprintf('task_done_%d', $task->getHousehold()->getId()),
            groupSummary: $task->getHousehold()->getName(),
        );
        $this->revokeTaskDue($task);
    }

    private function revokeTaskDue(Task $task): void
    {
        $household = $task->getHousehold();
        // Same audience as the original task_due — anyone else cannot have the notification anyway.
        $devices = filter(
            fn(Device $device) => $device->getUser()->getUserSettings()->notifyTaskDue === true,
            $this->deviceRepository->findByHousehold($household),
        );
        $this->revokeOnDevices(
            $devices,
            revokeGroupKey: sprintf('task_due_%d', $household->getId()),
            revokeEntityId: (string) $task->getId(),
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
            data: [
                'type' => 'task_due',
                'householdId' => (string) $household->getId(),
                'taskId' => (string) $task->getId(),
            ],
            groupKey: sprintf('task_due_%d', $household->getId()),
            groupSummary: $household->getName(),
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
            data: [
                'type' => 'finance_transaction',
                'householdId' => (string) $transaction->household->getId(),
                'transactionUuid' => $transaction->uuid,
            ],
            groupKey: sprintf('finance_transaction_%d', $transaction->household->getId()),
            groupSummary: $transaction->household->getName(),
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
            data: [
                'type' => 'finance_transaction',
                'householdId' => (string) $transaction->household->getId(),
                'transactionUuid' => $transaction->uuid,
            ],
            groupKey: sprintf('finance_transaction_%d', $transaction->household->getId()),
            groupSummary: $transaction->household->getName(),
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
            data: [
                'type' => 'invite',
                'householdId' => (string) $household->getId(),
            ],
            groupKey: sprintf('invite_%d', $household->getId()),
            groupSummary: $household->getName(),
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
            data: [
                'type' => 'task_assign',
                'householdId' => (string) $task->getHousehold()->getId(),
                'taskId' => (string) $task->getId(),
            ],
            groupKey: sprintf('task_assign_%d', $task->getHousehold()->getId()),
            groupSummary: $task->getHousehold()->getName(),
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
     * @param array<non-empty-string, string> $data
     * @param non-empty-string|null $groupKey Used by the Android `MessagingService` as the
     *        `setGroup()` key — notifications sharing this key bundle under a single drawer summary.
     * @param string|null $groupSummary Human-readable label shown on the bundle's summary
     *        notification (typically the household name).
     */
    private function publishToDevices(
        array   $devices,
        string  $title,
        string  $content,
        array   $data = [],
        ?string $groupKey = null,
        ?string $groupSummary = null,
    ): void {
        if (empty($devices)) {
            return;
        }
        $deviceIds = array_values(array_map(fn(Device $device) => $device->getPushId(), $devices));

        // Data-only payload: our custom Android FirebaseMessagingService builds notifications via
        // NotificationCompat so it can apply setGroup()/setGroupSummary(). Top-level `notification`
        // would make FCM auto-display in background and skip our service entirely.
        $payload = $data;
        $payload['title'] = $title;
        $payload['body'] = $content;
        if ($groupKey !== null) {
            $payload['groupKey'] = $groupKey;
        }
        if ($groupSummary !== null && $groupSummary !== '') {
            $payload['groupSummary'] = $groupSummary;
        }

        try {
            $message = CloudMessage::new()
                ->withData($payload)
                ->withAndroidConfig(AndroidConfig::new()->withHighMessagePriority());
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

    /**
     * Sends a data-only "revoke" message. Our Android service cancels the matching grouped
     * notification (and its summary if it has no siblings left).
     *
     * @param Device[] $devices
     * @param non-empty-string $revokeGroupKey
     * @param non-empty-string $revokeEntityId
     */
    private function revokeOnDevices(array $devices, string $revokeGroupKey, string $revokeEntityId): void
    {
        if (empty($devices)) {
            return;
        }
        $deviceIds = array_values(array_map(fn(Device $device) => $device->getPushId(), $devices));
        try {
            $message = CloudMessage::new()
                ->withData([
                    'revokeGroupKey' => $revokeGroupKey,
                    'revokeEntityId' => $revokeEntityId,
                ])
                ->withAndroidConfig(AndroidConfig::new()->withHighMessagePriority());
            $report = $this->messaging->sendMulticast($message, $deviceIds);
            $this->logger->info('Push revoke sent to {count} devices, {success} successful, {failed} failed!', [
                'count' => count($deviceIds),
                'success' => $report->successes()->count(),
                'failed' => $report->failures()->count(),
            ]);
        } catch (MessagingException|FirebaseException $e) {
            $this->logger->error('Could not send push revoke, reason: {message}!', [
                'message' => $e->getMessage(),
                'exception' => $e
            ]);
        }
    }
}
