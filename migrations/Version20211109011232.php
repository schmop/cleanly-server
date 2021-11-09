<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211109011232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE household_invite (token VARCHAR(255) NOT NULL, household_id INT DEFAULT NULL, valid_until DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_EEECD5C2E79FF843 (household_id), PRIMARY KEY(token)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT FK_EEECD5C2E79FF843 FOREIGN KEY (household_id) REFERENCES household (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE household_invite');
    }
}
