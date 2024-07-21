<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240720221526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checklist ALTER last_updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN checklist.last_updated_at IS \'\'');
        $this->addSql('ALTER TABLE checklist_subscriptions ALTER checklist_uuid TYPE VARCHAR');
        $this->addSql('ALTER TABLE household_invite ALTER valid_until TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN household_invite.valid_until IS \'\'');
        $this->addSql('ALTER TABLE refresh_token ALTER valid_until TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN refresh_token.valid_until IS \'\'');
        $this->addSql('ALTER TABLE registration ALTER registrated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN registration.registrated_at IS \'\'');
        $this->addSql('ALTER TABLE reset_password_request ALTER requested_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE reset_password_request ALTER expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN reset_password_request.requested_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN reset_password_request.expires_at IS \'\'');
        $this->addSql('ALTER TABLE task ALTER last_completed TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE task ALTER last_notified_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN task.last_completed IS \'\'');
        $this->addSql('COMMENT ON COLUMN task.last_notified_at IS \'\'');
        $this->addSql('ALTER TABLE task_log ALTER "timestamp" TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN task_log.timestamp IS \'\'');
        $this->addSql('ALTER TABLE todo ALTER checklist_uuid TYPE VARCHAR');
        $this->addSql('ALTER TABLE usage_log ALTER "timestamp" TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN usage_log.timestamp IS \'\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE household_invite ALTER valid_until TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN household_invite.valid_until IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE todo ALTER checklist_uuid TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE task_log ALTER timestamp TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN task_log."timestamp" IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE checklist_subscriptions ALTER checklist_uuid TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE refresh_token ALTER valid_until TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN refresh_token.valid_until IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE checklist ALTER last_updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN checklist.last_updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE task ALTER last_completed TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE task ALTER last_notified_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN task.last_completed IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task.last_notified_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE usage_log ALTER timestamp TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN usage_log."timestamp" IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE registration ALTER registrated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN registration.registrated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE reset_password_request ALTER requested_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE reset_password_request ALTER expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN reset_password_request.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reset_password_request.expires_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
