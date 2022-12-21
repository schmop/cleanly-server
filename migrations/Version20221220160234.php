<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221220160234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE household DROP picture');
        $this->addSql('ALTER TABLE household ADD webhook_secret VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE household ADD webhook_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE household ADD picture VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE household DROP webhook_url');
        $this->addSql('ALTER TABLE household DROP webhook_secret');
    }
}
