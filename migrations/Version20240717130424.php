<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240717130424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE usage_log DROP CONSTRAINT usage_log_pkey');
        $this->addSql('ALTER TABLE usage_log ADD uuid VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE usage_log ADD PRIMARY KEY (uuid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX usage_log_pkey');
        $this->addSql('ALTER TABLE usage_log DROP uuid');
        $this->addSql('ALTER TABLE usage_log ADD PRIMARY KEY (activity_type)');
    }
}
