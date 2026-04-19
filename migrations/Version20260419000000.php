<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace task.last_notified_at with nullable last_pushed_at and add reminder_config table with FK cascade and shape constraints';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD last_pushed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN task.last_pushed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE task SET last_pushed_at = last_notified_at');
        $this->addSql('ALTER TABLE task DROP last_notified_at');

        $this->addSql('CREATE SEQUENCE reminder_config_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql(<<<'SQL'
            CREATE TABLE reminder_config (
                id INT NOT NULL,
                task_id INT NOT NULL,
                type VARCHAR(32) NOT NULL,
                step_interval INT NOT NULL,
                reminder_time VARCHAR(5) NOT NULL,
                days_of_week SMALLINT DEFAULT NULL,
                month_day SMALLINT DEFAULT NULL,
                week_occurrence SMALLINT DEFAULT NULL,
                week_day SMALLINT DEFAULT NULL,
                year_month SMALLINT DEFAULT NULL,
                year_day SMALLINT DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX UNIQ_REMINDER_CONFIG_TASK ON reminder_config (task_id)');

        $this->addSql(<<<'SQL'
            ALTER TABLE reminder_config
            ADD CONSTRAINT FK_REMINDER_CONFIG_TASK
            FOREIGN KEY (task_id) REFERENCES task (id)
            ON UPDATE CASCADE ON DELETE CASCADE
            NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE reminder_config
            ADD CONSTRAINT CHK_REMINDER_TYPE
            CHECK (type IN ('daily', 'weekly', 'monthly_day', 'monthly_weekday', 'yearly'))
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE reminder_config
            ADD CONSTRAINT CHK_REMINDER_INTERVAL
            CHECK (step_interval >= 1)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE reminder_config
            ADD CONSTRAINT CHK_REMINDER_TIME_FORMAT
            CHECK (reminder_time ~ '^([01][0-9]|2[0-3]):[0-5][0-9]$')
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE reminder_config
            ADD CONSTRAINT CHK_REMINDER_SHAPE
            CHECK (
                CASE type
                    WHEN 'daily' THEN
                        days_of_week IS NULL
                        AND month_day IS NULL
                        AND week_occurrence IS NULL
                        AND week_day IS NULL
                        AND year_month IS NULL
                        AND year_day IS NULL
                    WHEN 'weekly' THEN
                        days_of_week BETWEEN 1 AND 127
                        AND month_day IS NULL
                        AND week_occurrence IS NULL
                        AND week_day IS NULL
                        AND year_month IS NULL
                        AND year_day IS NULL
                    WHEN 'monthly_day' THEN
                        month_day BETWEEN 1 AND 31
                        AND days_of_week IS NULL
                        AND week_occurrence IS NULL
                        AND week_day IS NULL
                        AND year_month IS NULL
                        AND year_day IS NULL
                    WHEN 'monthly_weekday' THEN
                        week_occurrence BETWEEN -4 AND 4
                        AND week_occurrence <> 0
                        AND week_day BETWEEN 0 AND 6
                        AND days_of_week IS NULL
                        AND month_day IS NULL
                        AND year_month IS NULL
                        AND year_day IS NULL
                    WHEN 'yearly' THEN
                        year_month BETWEEN 1 AND 12
                        AND year_day BETWEEN 1 AND 31
                        AND days_of_week IS NULL
                        AND month_day IS NULL
                        AND week_occurrence IS NULL
                        AND week_day IS NULL
                    ELSE FALSE
                END
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reminder_config');
        $this->addSql('DROP SEQUENCE reminder_config_id_seq CASCADE');
        $this->addSql('ALTER TABLE task DROP last_pushed_at');
        $this->addSql('ALTER TABLE task ADD last_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('COMMENT ON COLUMN task.last_notified_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
