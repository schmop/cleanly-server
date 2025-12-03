<?php

namespace App\Finance;

use App\Utils\PriorityQueue;

class DebtFlowMinimizer
{
    /**
     * Minimize debts by using the pooling algorithm
     * Motivation: Instead of owing multiple people and receiving money from multiple people,
     * each participant receives or owes a single amount to the household "pool".
     *
     * First add all outgoing and ingoing edges of the transaction graph in order to calculate net amounts
     * Then split the participants into debtors and creditors based on their net amounts (positive or negative)
     *
     * Iterate over highest debtors and creditors to minimize the number of transactions needed to settle all debts
     *
     * @param list<Debt> $debts
     * @return list<Debt>
     */
    public function minimizeDebts(array $debts): array
    {
        /** @var PriorityQueue<int, DebtNetAmount> $debtorsQueue */
        $debtorsQueue = new PriorityQueue();
        /** @var PriorityQueue<int, DebtNetAmount> $creditorsQueue */
        $creditorsQueue = new PriorityQueue();
        $participants = $this->collectParticipants($debts);

        // first calculate the total amount each user owes or is owed
        foreach($participants as $participant) {
            $netAmount = 0;
            foreach ($debts as $debt) {
                if ($debt->debtorId === $participant) {
                    $netAmount -= $debt->amount;
                } elseif ($debt->creditorId === $participant) {
                    $netAmount += $debt->amount;
                }
            }
            if ($netAmount > 0) {
                $creditorsQueue->push(new DebtNetAmount($participant, $netAmount), $netAmount);
            } elseif ($netAmount < 0) {
                $netLoss = abs($netAmount);
                $debtorsQueue->push(new DebtNetAmount($participant, $netLoss), $netLoss);
            }
        }
        $minimizedDebts = [];
        // todo: ponder if it is possible to have an empty creditorsQueue when debtorsQueue is not empty
        while (!$debtorsQueue->isEmpty() && !$creditorsQueue->isEmpty()) {
            $debtor = $debtorsQueue->pop();
            $creditor = $creditorsQueue->pop();

            $settlementAmount = min($debtor->amount, $creditor->amount);
            $minimizedDebts[] = new Debt(
                creditorId: $creditor->userId,
                debtorId: $debtor->userId,
                amount: $settlementAmount,
            );

            $remainingDebtorAmount = $debtor->amount - $settlementAmount;
            if ($remainingDebtorAmount > 0) {
                $debtorsQueue->push(new DebtNetAmount($debtor->userId, $remainingDebtorAmount), $remainingDebtorAmount);
            }

            $remainingCreditorAmount = $creditor->amount - $settlementAmount;
            if ($remainingCreditorAmount > 0) {
                $creditorsQueue->push(new DebtNetAmount($creditor->userId, $remainingCreditorAmount), $remainingCreditorAmount);
            }

        }

        return $minimizedDebts;
    }

    /**
     * @param list<Debt> $debts
     * @return list<int>
     */
    private function collectParticipants(array $debts): array
    {
        $participants = [];
        foreach ($debts as $debt) {
            if (!in_array($debt->debtorId, $participants, true)) {
                $participants[] = $debt->debtorId;
            }
            if (!in_array($debt->creditorId, $participants, true)) {
                $participants[] = $debt->creditorId;
            }
        }

        return $participants;
    }
}