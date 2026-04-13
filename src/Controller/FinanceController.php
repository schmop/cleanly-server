<?php

namespace App\Controller;

use App\Finance\FinanceTransactionPublisher;
use App\Finance\TransactionFactory;
use App\Finance\TransactionRepository;
use App\Household\Entity\Household;
use App\Household\HouseholdVoter;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\Push\Pusher;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class FinanceController extends UserAwareController
{
    #[Route(path: '/api/household/{id}/finance/transaction/add', name: 'household_finance_transaction_add', methods: ['PUT'])]
    public function addTransaction(
        Household                   $household,
        TransactionFactory          $transactionFactory,
        TransactionRepository       $transactionRepository,
        Pusher                      $pusher,
        FinanceTransactionPublisher $financeTransactionPublisher,
        Request                     $request,
        LoggerInterface             $logger,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::ADD_FINANCE_TRANSACTIONS, $household);
        try {
            $data = Json::fromRequest($request);
            $transaction = $transactionFactory->transactionFromJson($data, $household);
            $transactionRepository->saveWithShares($transaction);
            $pusher->publishNewFinanceTransaction($transaction, $this->getUser());
            $financeTransactionPublisher->publish($transaction, $this->getUser());

        } catch (UnexpectedJsonException $e) {
            $logger->error('Failed to add transaction: ' . $e->getMessage());
            return JsonErrorResponse::create([
                'reason' => 'Invalid data: ' . $e->getMessage(),
            ]);
        }

        return JsonSuccessResponse::create();
    }

    #[Route(path: '/api/household/{id}/finance/transaction/{uuid}', name: 'household_finance_transaction_delete', methods: ['DELETE'])]
    public function deleteTransaction(
        Household                   $household,
        string                      $uuid,
        TransactionRepository       $transactionRepository,
        FinanceTransactionPublisher $financeTransactionPublisher,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::ADD_FINANCE_TRANSACTIONS, $household);

        $transaction = $transactionRepository->find($uuid);
        if ($transaction === null || $transaction->household->getId() !== $household->getId()) {
            return JsonErrorResponse::create(['reason' => 'Transaction not found.'], 404);
        }

        $twoHoursAgo = new \DateTimeImmutable('-2 hours');
        if ($transaction->createdAt < $twoHoursAgo) {
            return JsonErrorResponse::create(['reason' => 'Transaction can only be deleted within 2 hours of creation.'], 403);
        }

        $financeTransactionPublisher->publishDelete($transaction, $this->getUser());
        $transactionRepository->remove($transaction);

        return JsonSuccessResponse::create();
    }

    #[Route(path: '/api/household/{id}/finance/transaction/{uuid}', name: 'household_finance_transaction_update', methods: ['PUT'])]
    public function updateTransaction(
        Household                   $household,
        string                      $uuid,
        TransactionFactory          $transactionFactory,
        TransactionRepository       $transactionRepository,
        FinanceTransactionPublisher $financeTransactionPublisher,
        Request                     $request,
        LoggerInterface             $logger,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::ADD_FINANCE_TRANSACTIONS, $household);

        $existing = $transactionRepository->find($uuid);
        if ($existing === null || $existing->household->getId() !== $household->getId()) {
            return JsonErrorResponse::create(['reason' => 'Transaction not found.'], 404);
        }

        $twoHoursAgo = new \DateTimeImmutable('-2 hours');
        if ($existing->createdAt < $twoHoursAgo) {
            return JsonErrorResponse::create(['reason' => 'Transaction can only be edited within 2 hours of creation.'], 403);
        }

        try {
            $data = Json::fromRequest($request);
            $updated = $transactionFactory->transactionFromJson($data, $household, $existing->createdAt);
            $transactionRepository->remove($existing);
            $transactionRepository->saveWithShares($updated);
            $financeTransactionPublisher->publishUpdate($updated, $this->getUser());
        } catch (UnexpectedJsonException $e) {
            $logger->error('Failed to update transaction: ' . $e->getMessage());
            return JsonErrorResponse::create([
                'reason' => 'Invalid data: ' . $e->getMessage(),
            ]);
        }

        return JsonSuccessResponse::create();
    }

    #[Route(path: '/api/household/{id}/finance/transactions', name: 'household_finance_transaction_get', methods: ['GET'])]
    public function getTransactions(
        Household $household,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::READ_HOUSEHOLD_CONTENTS, $household);

        return JsonSuccessResponse::create([
            'transactions' => array_map(
                fn($transaction) => $transaction->jsonSerialize(),
                $household->getTransactions()->toArray(),
            ),
        ]);
    }

    #[Route(path: '/api/household/{id}/finance/summary', name: 'household_finance_summary_get', methods: ['GET'])]
    public function getFinanceSummary(
        Household             $household,
        TransactionRepository $transactionRepository,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::READ_HOUSEHOLD_CONTENTS, $household);
        $income = $transactionRepository->getTotalIncomeForUserInHousehold($this->getUser(), $household);
        $expense = $transactionRepository->getTotalExpenseForUserInHousehold($this->getUser(), $household);

        return JsonSuccessResponse::create([
            'totalCosts' => $transactionRepository->getTotalCostsForHousehold($household),
            'yourCost' => $expense - $income,
            'yourIncome' => $income,
            'yourExpense' => $expense,
            'debts' => $transactionRepository->getDebtsInHousehold($household),
        ]);
    }
}