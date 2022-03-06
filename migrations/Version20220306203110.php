<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220306203110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE household_invite ADD invitee_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT FK_EEECD5C27A512022 FOREIGN KEY (invitee_id) REFERENCES household (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EEECD5C27A512022 ON household_invite (invitee_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE household_invite DROP CONSTRAINT FK_EEECD5C27A512022');
        $this->addSql('DROP INDEX IDX_EEECD5C27A512022');
        $this->addSql('ALTER TABLE household_invite DROP invitee_id');
    }
}
