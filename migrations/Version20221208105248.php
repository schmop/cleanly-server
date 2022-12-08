<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221208105248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE household_invite ALTER household_id SET NOT NULL');
        $this->addSql('ALTER TABLE refresh_token ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE task ALTER household_id SET NOT NULL');
        $this->addSql('ALTER TABLE task_log ADD stars INT');
        $this->addSql('UPDATE task_log SET stars = task.stars FROM task WHERE task_log.task_id = task.id');
        $this->addSql('ALTER TABLE task_log ALTER stars SET NOT NULL');
        $this->addSql('ALTER TABLE task_log ALTER task_id SET NOT NULL');
        $this->addSql('ALTER TABLE task_log ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE task_log ALTER "timestamp" SET NOT NULL');
        $this->addSql('ALTER TABLE todo ALTER household_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE task_log DROP stars');
        $this->addSql('ALTER TABLE task_log ALTER task_id DROP NOT NULL');
        $this->addSql('ALTER TABLE task_log ALTER user_id DROP NOT NULL');
        $this->addSql('ALTER TABLE task_log ALTER timestamp DROP NOT NULL');
        $this->addSql('ALTER TABLE refresh_token ALTER user_id DROP NOT NULL');
        $this->addSql('ALTER TABLE task ALTER household_id DROP NOT NULL');
        $this->addSql('ALTER TABLE todo ALTER household_id DROP NOT NULL');
        $this->addSql('ALTER TABLE household_invite ALTER household_id DROP NOT NULL');
    }
}
