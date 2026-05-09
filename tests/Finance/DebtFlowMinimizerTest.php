<?php

declare(strict_types=1);

namespace App\Tests\Finance;

use App\Finance\Debt;
use App\Finance\DebtFlowMinimizer;
use PHPUnit\Framework\TestCase;

class DebtFlowMinimizerTest extends TestCase
{
    private DebtFlowMinimizer $minimizer;

    protected function setUp(): void
    {
        $this->minimizer = new DebtFlowMinimizer();
    }

    public function testEmptyDebtsReturnsEmpty(): void
    {
        $this->assertSame([], $this->minimizer->minimizeDebts([]));
    }

    public function testSingleDebtIsPreserved(): void
    {
        $result = $this->minimizer->minimizeDebts([
            new Debt(creditorId: 1, debtorId: 2, amount: 100),
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]->creditorId);
        $this->assertSame(2, $result[0]->debtorId);
        $this->assertSame(100, $result[0]->amount);
    }

    public function testTwoEqualOpposingDebtsCancelOut(): void
    {
        // A owes B 100, B owes A 100 -> nothing left to settle.
        $result = $this->minimizer->minimizeDebts([
            new Debt(creditorId: 1, debtorId: 2, amount: 100),
            new Debt(creditorId: 2, debtorId: 1, amount: 100),
        ]);

        $this->assertSame([], $result);
    }

    public function testTwoOpposingDebtsNetToDifference(): void
    {
        // A owes B 100, B owes A 30 -> A still owes B 70.
        $result = $this->minimizer->minimizeDebts([
            new Debt(creditorId: 1, debtorId: 2, amount: 100),
            new Debt(creditorId: 2, debtorId: 1, amount: 30),
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]->creditorId);
        $this->assertSame(2, $result[0]->debtorId);
        $this->assertSame(70, $result[0]->amount);
    }

    public function testMultipleDebtsBetweenSamePairAggregate(): void
    {
        $result = $this->minimizer->minimizeDebts([
            new Debt(creditorId: 1, debtorId: 2, amount: 50),
            new Debt(creditorId: 1, debtorId: 2, amount: 30),
            new Debt(creditorId: 1, debtorId: 2, amount: 20),
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(100, $result[0]->amount);
    }

    public function testThreePartyCircularDebtCollapsesToFewerEdges(): void
    {
        // A->B 100, B->C 100, C->A 100 — net is zero everywhere.
        $result = $this->minimizer->minimizeDebts([
            new Debt(creditorId: 2, debtorId: 1, amount: 100),
            new Debt(creditorId: 3, debtorId: 2, amount: 100),
            new Debt(creditorId: 1, debtorId: 3, amount: 100),
        ]);

        $this->assertSame([], $result);
    }

    public function testOneDebtorOwesTwoCreditors(): void
    {
        // user 1 owes user 2 60 and user 3 40.
        $result = $this->minimizer->minimizeDebts([
            new Debt(creditorId: 2, debtorId: 1, amount: 60),
            new Debt(creditorId: 3, debtorId: 1, amount: 40),
        ]);

        $this->assertCount(2, $result);
        $totalOwed = array_sum(array_map(fn(Debt $d) => $d->amount, $result));
        $this->assertSame(100, $totalOwed);
        foreach ($result as $debt) {
            $this->assertSame(1, $debt->debtorId);
        }
    }

    public function testTransitiveDebtIsCollapsed(): void
    {
        // A owes B 100, B owes C 100. A should pay C directly; B becomes neutral.
        $result = $this->minimizer->minimizeDebts([
            new Debt(creditorId: 2, debtorId: 1, amount: 100),
            new Debt(creditorId: 3, debtorId: 2, amount: 100),
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]->debtorId);
        $this->assertSame(3, $result[0]->creditorId);
        $this->assertSame(100, $result[0]->amount);
    }

    public function testMinimizationReducesEdgeCount(): void
    {
        // The classic 3-person dinner: each person pays a shared portion.
        // Without pooling there are many edges; with pooling, at most 2.
        $debts = [
            new Debt(creditorId: 1, debtorId: 2, amount: 30),
            new Debt(creditorId: 1, debtorId: 3, amount: 30),
            new Debt(creditorId: 2, debtorId: 3, amount: 30),
        ];

        $result = $this->minimizer->minimizeDebts($debts);

        $this->assertLessThanOrEqual(2, count($result));
        $this->assertNetBalancesPreserved($debts, $result);
    }

    public function testNetBalancesPreservedAcrossManyParticipants(): void
    {
        // 5 participants, mixed flows. Verify each user's net balance is preserved.
        $debts = [
            new Debt(creditorId: 1, debtorId: 2, amount: 200),
            new Debt(creditorId: 1, debtorId: 3, amount: 150),
            new Debt(creditorId: 2, debtorId: 4, amount: 80),
            new Debt(creditorId: 3, debtorId: 5, amount: 50),
            new Debt(creditorId: 4, debtorId: 5, amount: 100),
            new Debt(creditorId: 5, debtorId: 1, amount: 30),
        ];

        $result = $this->minimizer->minimizeDebts($debts);

        $this->assertNetBalancesPreserved($debts, $result);
        // Bound: minimized output should never exceed (participants - 1) edges.
        $this->assertLessThanOrEqual(4, count($result));
    }

    public function testCreditorAmountsAreTrackedWhenLargerThanDebtor(): void
    {
        // user 1 owes 50, user 2 is owed 100 -> user 2 still owed 50 by remaining debtor.
        $result = $this->minimizer->minimizeDebts([
            new Debt(creditorId: 2, debtorId: 1, amount: 50),
            new Debt(creditorId: 2, debtorId: 3, amount: 50),
        ]);

        $this->assertCount(2, $result);
        foreach ($result as $debt) {
            $this->assertSame(2, $debt->creditorId);
        }
    }

    public function testNoSelfDebtsEmitted(): void
    {
        $result = $this->minimizer->minimizeDebts([
            new Debt(creditorId: 1, debtorId: 2, amount: 100),
            new Debt(creditorId: 2, debtorId: 1, amount: 60),
            new Debt(creditorId: 3, debtorId: 1, amount: 40),
        ]);

        foreach ($result as $debt) {
            $this->assertNotSame($debt->creditorId, $debt->debtorId);
        }
    }

    /**
     * @param list<Debt> $original
     * @param list<Debt> $minimized
     */
    private function assertNetBalancesPreserved(array $original, array $minimized): void
    {
        $this->assertSame(
            $this->netBalances($original),
            $this->netBalances($minimized),
            'Per-user net balances must match between original and minimized debts',
        );
    }

    /**
     * @param list<Debt> $debts
     * @return array<int, int>
     */
    private function netBalances(array $debts): array
    {
        $net = [];
        foreach ($debts as $debt) {
            $net[$debt->creditorId] = ($net[$debt->creditorId] ?? 0) + $debt->amount;
            $net[$debt->debtorId] = ($net[$debt->debtorId] ?? 0) - $debt->amount;
        }
        $net = array_filter($net, fn(int $v) => $v !== 0);
        ksort($net);

        return $net;
    }
}
