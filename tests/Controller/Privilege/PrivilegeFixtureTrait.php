<?php

declare(strict_types=1);

namespace App\Tests\Controller\Privilege;

use AlexCrawford\LexoRank\Rank;
use App\Finance\Entity\Transaction;
use App\Finance\Entity\TransactionShare;
use App\Finance\TransactionType;
use App\Household\Entity\Household;
use App\Household\Entity\HouseholdPrivilege;
use App\Household\ReassignmentStrategy;
use App\Task\Entity\Task;
use App\Todo\Entity\Checklist;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Shared fixtures for privilege-gated endpoint tests. Each test tracks the
 * users and households it created so tearDown can remove them (cascades pick
 * up child rows).
 */
trait PrivilegeFixtureTrait
{
    private EntityManagerInterface $em;

    /** @var int[] */
    private array $householdIds = [];

    /** @var int[] */
    private array $userIds = [];

    private string $runId = '';

    protected function initFixtures(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->runId = substr(md5(uniqid('', true)), 0, 8);
        $this->householdIds = [];
        $this->userIds = [];
    }

    protected function cleanupFixtures(): void
    {
        // Re-resolve the EM in case the current one was closed by a failed flush.
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // checklist_subscriptions has no ON DELETE CASCADE from either side, so
        // clear it explicitly before removing households/users.
        if ($this->userIds !== []) {
            $em->getConnection()->executeStatement(
                'DELETE FROM checklist_subscriptions WHERE user_id IN (:ids)',
                ['ids' => $this->userIds],
                ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER],
            );
        }

        foreach ($this->householdIds as $id) {
            $h = $em->find(Household::class, $id);
            if ($h !== null) {
                $em->remove($h);
            }
        }
        foreach ($this->userIds as $id) {
            $u = $em->find(User::class, $id);
            if ($u !== null) {
                $em->remove($u);
            }
        }
        $em->flush();
    }

    private function createUser(?string $emailHint = null): User
    {
        $email = sprintf('%s_%s@test.example', $emailHint ?? 'u', uniqid('', true));
        $user = new User($email, 'Test User');
        $user->setPassword('irrelevant');
        $this->em->persist($user);
        $this->em->flush();
        $this->userIds[] = (int)$user->getId();

        return $user;
    }

    private function createHousehold(User $admin): Household
    {
        $household = new Household();
        $household->setName('Privilege Test Household ' . $this->runId);
        $household->setReassignmentStrategy(ReassignmentStrategy::None);
        $household->addMember($admin);
        $household->setUserPrivilege($admin, HouseholdPrivilege::PRIVILEGE_ADMIN);
        $this->em->persist($household);
        $this->em->flush();
        $this->householdIds[] = (int)$household->getId();

        return $household;
    }

    private function addMember(Household $household, int $privilege, ?string $emailHint = null): User
    {
        $user = $this->createUser($emailHint);
        $household->addMember($user);
        $household->setUserPrivilege($user, $privilege);
        $this->em->flush();

        return $user;
    }

    private function createTask(Household $household, string $name = 'Test Task'): Task
    {
        $task = new Task();
        $task->setHousehold($household);
        $task->setName($name);
        $task->setStars(1);
        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }

    private function createChecklist(Household $household, string $name = 'Test Checklist'): Checklist
    {
        $checklist = new Checklist(
            uniqid('cl_', true),
            $name,
            $household,
            new \DateTimeImmutable(),
            Rank::forEmptySequence()->get(),
        );
        $this->em->persist($checklist);
        $this->em->flush();

        return $checklist;
    }

    private function createTransaction(Household $household, User $sender, \DateTimeImmutable $createdAt): Transaction
    {
        $transaction = new Transaction(
            uuid: uniqid('tx_', true),
            title: 'Test Transaction',
            sender: $sender,
            household: $household,
            amount: 1000,
            transactionType: TransactionType::Expense,
            date: new \DateTimeImmutable(),
            createdAt: $createdAt,
        );
        $share = new TransactionShare(
            uuid: uniqid('ts_', true),
            user: $sender,
            share: 1,
            transaction: $transaction,
        );
        $transaction->addShare($share);
        $this->em->persist($transaction);
        $this->em->persist($share);
        $this->em->flush();

        return $transaction;
    }
}
