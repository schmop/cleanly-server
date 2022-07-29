<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220729222159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE household_invite DROP CONSTRAINT FK_EEECD5C2E79FF843');
        $this->addSql('ALTER TABLE household_invite DROP CONSTRAINT FK_EEECD5C27A512022');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT FK_EEECD5C2E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT FK_EEECD5C27A512022 FOREIGN KEY (invitee_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE household_invite DROP CONSTRAINT fk_eeecd5c2e79ff843');
        $this->addSql('ALTER TABLE household_invite DROP CONSTRAINT fk_eeecd5c27a512022');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT fk_eeecd5c2e79ff843 FOREIGN KEY (household_id) REFERENCES household (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT fk_eeecd5c27a512022 FOREIGN KEY (invitee_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
