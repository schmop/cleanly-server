<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240717125406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE usage_log (activity_type VARCHAR(255) NOT NULL, user_id INT NOT NULL, timestamp TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(activity_type))');
        $this->addSql('CREATE INDEX IDX_F4102AEAA76ED395 ON usage_log (user_id)');
        $this->addSql('COMMENT ON COLUMN usage_log.timestamp IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE usage_log ADD CONSTRAINT FK_F4102AEAA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE usage_log DROP CONSTRAINT FK_F4102AEAA76ED395');
        $this->addSql('DROP TABLE usage_log');
    }
}
