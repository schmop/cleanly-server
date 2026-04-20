<?php

namespace App\Finance;

use App\Finance\Entity\Transaction;
use App\Household\Entity\Household;
use App\Persistence\PersistenceException;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
        private readonly DebtFlowMinimizer $debtFlowMinimizer,
    ) {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @throws PersistenceException
     */
    public function saveWithShares(Transaction $transaction): void
    {
        PersistenceException::wrap(function () use ($transaction): void {
            $em = $this->getEntityManager();
            $em->persist($transaction);
            foreach ($transaction->shares as $share) {
                $em->persist($share);
            }
            $em->flush();
        });
    }

    /**
     * @throws PersistenceException
     */
    public function remove(Transaction $transaction): void
    {
        PersistenceException::removeAndFlush($this->getEntityManager(), $transaction);
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getTotalCostsForHousehold(Household $household): int
    {
        // add expenses and subtract incomes but ignore transfers
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->select('SUM(CASE WHEN t.transactionType = :expense THEN t.amount WHEN t.transactionType = :income THEN -t.amount ELSE 0 END) as total')
            ->from(Transaction::class, 't')
            ->where('t.household = :household')
            ->setParameter(':household', $household)
            ->setParameter(':expense', TransactionType::Expense)
            ->setParameter(':income', TransactionType::Income);
        $result = $qb->getQuery()->getSingleScalarResult();

        return (int)$result;
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getTotalIncomeForUserInHousehold(User $user, Household $household): int
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('sum', 'sum');
        // select SUM(CASE WHEN transaction_type = 'expense' THEN amount * share / (SELECT SUM(s2.share) from transaction_share s2 WHERE s2.transaction_uuid = t.uuid) END) from transaction t JOIN transaction_share s ON t.uuid = s.transaction_uuid WHERE s.user_id = 3;
        $query = $this->getEntityManager()->createNativeQuery(<<<EOF
SELECT SUM(CASE
    WHEN t.transaction_type = :income THEN t.amount * s.share / (
        SELECT SUM(s2.share)
        FROM transaction_share s2
        WHERE s2.transaction_uuid = t.uuid
)
    WHEN t.transaction_type = :transfer AND t.user_id <> :user THEN t.amount
    END
)
FROM transaction t
LEFT JOIN transaction_share s ON t.uuid = s.transaction_uuid
WHERE t.household_id = :household
AND s.user_id = :user;
EOF, $rsm)
            ->setParameter('user', $user->getId())
            ->setParameter('household', $household->getId())
            ->setParameter('income', TransactionType::Income)
            ->setParameter('transfer', TransactionType::Transfer)
        ;

        return (int)$query->getSingleScalarResult();
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getTotalExpenseForUserInHousehold(User $user, Household $household): int
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('sum', 'sum');
        // select SUM(CASE WHEN transaction_type = 'expense' THEN amount * share / (SELECT SUM(s2.share) from transaction_share s2 WHERE s2.transaction_uuid = t.uuid) END) from transaction t JOIN transaction_share s ON t.uuid = s.transaction_uuid WHERE s.user_id = 3;
        $query = $this->getEntityManager()->createNativeQuery(<<<EOF
SELECT SUM(CASE
    WHEN t.transaction_type = :expense AND s.user_id = :user THEN t.amount * s.share / (
        SELECT SUM(s2.share)
        FROM transaction_share s2
        WHERE s2.transaction_uuid = t.uuid
)
    WHEN t.transaction_type = :transfer AND s.user_id <> :user AND t.user_id = :user THEN t.amount
    END
)
FROM transaction t
LEFT JOIN transaction_share s ON t.uuid = s.transaction_uuid
WHERE t.household_id = :household;
EOF, $rsm)
            ->setParameter('user', $user->getId())
            ->setParameter('household', $household->getId())
            ->setParameter('expense', TransactionType::Expense)
            ->setParameter('transfer', TransactionType::Transfer)
        ;

        return (int)$query->getSingleScalarResult();
    }

    /**
     * @return list<Debt>
     * @throws \RuntimeException
     */
    public function getDebtsInHousehold(Household $household): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('debtor', 'debtor', Types::INTEGER);
        $rsm->addScalarResult('creditor', 'creditor', Types::INTEGER);
        $rsm->addScalarResult('amount', 'amount', Types::INTEGER);
        /**
         * Select cross product of all members in household
         * Then for each pair calculate how much the debtor owes the creditor using subquery
         */
        $query = $this->getEntityManager()->createNativeQuery(<<<EOF
SELECT
    m1.user_id AS debtor,
    m2.user_id AS creditor,
    COALESCE((
                 SELECT SUM(
                    CASE WHEN (t.transaction_type = :expense OR t.transaction_type = :transfer)
                                AND s1.user_id = m1.user_id
                                AND t.user_id = m2.user_id
                            THEN t.amount * s1.share / total_shares.total_share
                         WHEN t.transaction_type = :income
                                AND t.user_id = m1.user_id
                                AND s1.user_id = m2.user_id
                            THEN t.amount * s1.share / total_shares.total_share
                    END
                )
                 FROM transaction t
                          JOIN transaction_share s1 ON t.uuid = s1.transaction_uuid
                          JOIN (
                     SELECT s2.transaction_uuid, SUM(s2.share) AS total_share
                     FROM transaction_share s2
                     GROUP BY s2.transaction_uuid
                 ) AS total_shares ON total_shares.transaction_uuid = t.uuid
                     AND t.household_id = :household
             ), 0) AS amount
FROM household_members m1 CROSS JOIN household_members m2
WHERE m1.household_id = :household AND m2.household_id = :household
  AND m1.user_id <> m2.user_id
;
EOF, $rsm)
            ->setParameter('household', $household->getId())
            ->setParameter('expense', TransactionType::Expense)
            ->setParameter('income', TransactionType::Income)
            ->setParameter('transfer', TransactionType::Transfer)
        ;
        $results = $query->getResult();
        if (!is_array($results)) {
            $this->logger->error("Unexpected result from debt query", ['result' => $results]);
            throw new \RuntimeException('Unexpected result from debt query');
        }
        $debts = array_values(array_map(
            /** @param array{creditor: int, debtor: int, amount: int} $row */
            function (mixed $row): Debt {
                if (!is_array($row)
                    || !isset($row['creditor'], $row['debtor'], $row['amount'])
                    || !is_int($row['creditor'])
                    || !is_int($row['debtor'])
                    || !is_int($row['amount'])
                ) {
                    $this->logger->error("Unexpected row format in debt query result", ['row' => $row]);
                    throw new \RuntimeException('Unexpected row format in debt query result');
                }
                return new Debt(
                    creditorId: $row['creditor'],
                    debtorId: $row['debtor'],
                    amount: $row['amount'],
                );
            },
            $results,
        ));

        return $this->debtFlowMinimizer->minimizeDebts($debts);
    }
}
