<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use AlexCrawford\LexoRank\Rank;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-user ordering for households on the dashboard lives on a dedicated
 * household_rank table so it stays decoupled from household_privilege
 * (permission level). Every existing (user, household) membership gets a
 * rank row via backfill.
 *
 * Runs via $this->connection because the backfill reads and writes within
 * the same migration.
 */
final class Version20260420120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create household_rank table for per-user household ordering';
    }

    public function up(Schema $schema): void
    {
        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE household_rank (
                household INT NOT NULL,
                "user" INT NOT NULL,
                sort_rank VARCHAR(255) COLLATE "C" NOT NULL,
                PRIMARY KEY (household, "user")
            )
        SQL);

        $this->connection->executeStatement(
            'CREATE INDEX IDX_HOUSEHOLD_RANK_HOUSEHOLD ON household_rank (household)'
        );
        $this->connection->executeStatement(
            'CREATE INDEX IDX_HOUSEHOLD_RANK_USER ON household_rank ("user")'
        );

        $this->connection->executeStatement(<<<'SQL'
            ALTER TABLE household_rank
            ADD CONSTRAINT FK_HOUSEHOLD_RANK_HOUSEHOLD
            FOREIGN KEY (household) REFERENCES household (id)
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->connection->executeStatement(<<<'SQL'
            ALTER TABLE household_rank
            ADD CONSTRAINT FK_HOUSEHOLD_RANK_USER
            FOREIGN KEY ("user") REFERENCES "user" (id)
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->connection->executeStatement(<<<'SQL'
            ALTER TABLE household_rank
            ADD CONSTRAINT CHK_HOUSEHOLD_RANK_SORT_RANK
            CHECK (sort_rank <> '')
        SQL);

        // Backfill: every existing membership gets a rank, assigned sequentially
        // per user in household-id order.
        $userIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT user_id FROM household_members ORDER BY user_id'
        );
        foreach ($userIds as $userId) {
            $householdIds = $this->connection->fetchFirstColumn(
                'SELECT household_id FROM household_members WHERE user_id = :user ORDER BY household_id',
                ['user' => $userId],
            );
            $rank = Rank::forEmptySequence();
            foreach ($householdIds as $householdId) {
                $this->connection->executeStatement(
                    'INSERT INTO household_rank (household, "user", sort_rank) VALUES (:household, :user, :rank)',
                    ['household' => $householdId, 'user' => $userId, 'rank' => $rank->get()],
                );
                $rank = Rank::after($rank);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE household_rank');
    }
}
