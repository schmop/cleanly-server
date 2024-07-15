<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240715010642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE checklist_subscriptions (subscribers VARCHAR(255) NOT NULL, user_id INT NOT NULL, PRIMARY KEY(subscribers, user_id))');
        $this->addSql('CREATE INDEX IDX_BE57A55B2FCD16AC ON checklist_subscriptions (subscribers)');
        $this->addSql('CREATE INDEX IDX_BE57A55BA76ED395 ON checklist_subscriptions (user_id)');
        $this->addSql('ALTER TABLE checklist_subscriptions ADD CONSTRAINT FK_BE57A55B2FCD16AC FOREIGN KEY (subscribers) REFERENCES checklist (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE checklist_subscriptions ADD CONSTRAINT FK_BE57A55BA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE checklist_subscriptions DROP CONSTRAINT FK_BE57A55B2FCD16AC');
        $this->addSql('ALTER TABLE checklist_subscriptions DROP CONSTRAINT FK_BE57A55BA76ED395');
        $this->addSql('DROP TABLE checklist_subscriptions');
    }
}
