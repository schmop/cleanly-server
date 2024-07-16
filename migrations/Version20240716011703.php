<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240716011703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checklist_subscriptions DROP CONSTRAINT fk_be57a55b2fcd16ac');
        $this->addSql('DROP INDEX idx_be57a55b2fcd16ac');
        $this->addSql('ALTER TABLE checklist_subscriptions DROP CONSTRAINT checklist_subscriptions_pkey');
        $this->addSql('ALTER TABLE checklist_subscriptions RENAME COLUMN subscribers TO checklist_uuid');
        $this->addSql('ALTER TABLE checklist_subscriptions ADD CONSTRAINT FK_BE57A55B578F5541 FOREIGN KEY (checklist_uuid) REFERENCES checklist (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_BE57A55B578F5541 ON checklist_subscriptions (checklist_uuid)');
        $this->addSql('ALTER TABLE checklist_subscriptions ADD PRIMARY KEY (checklist_uuid, user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE checklist_subscriptions DROP CONSTRAINT FK_BE57A55B578F5541');
        $this->addSql('DROP INDEX IDX_BE57A55B578F5541');
        $this->addSql('DROP INDEX checklist_subscriptions_pkey');
        $this->addSql('ALTER TABLE checklist_subscriptions RENAME COLUMN checklist_uuid TO subscribers');
        $this->addSql('ALTER TABLE checklist_subscriptions ADD CONSTRAINT fk_be57a55b2fcd16ac FOREIGN KEY (subscribers) REFERENCES checklist (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_be57a55b2fcd16ac ON checklist_subscriptions (subscribers)');
        $this->addSql('ALTER TABLE checklist_subscriptions ADD PRIMARY KEY (subscribers, user_id)');
    }
}
