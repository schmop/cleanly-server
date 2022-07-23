<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220720161926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE task_log (uuid VARCHAR(255) NOT NULL, task_id INT DEFAULT NULL, user_id INT DEFAULT NULL, timestamp TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX IDX_E0BD90428DB60186 ON task_log (task_id)');
        $this->addSql('CREATE INDEX IDX_E0BD9042A76ED395 ON task_log (user_id)');
        $this->addSql('COMMENT ON COLUMN task_log.timestamp IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE task_log ADD CONSTRAINT FK_E0BD90428DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_log ADD CONSTRAINT FK_E0BD9042A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE task_log');
    }
}
