<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220730020911 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE registration (uuid VARCHAR(255) NOT NULL, mail VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, token VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, registrated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_62A8A7A75126AC48 ON registration (mail)');
        $this->addSql('COMMENT ON COLUMN registration.registrated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE household_invite DROP CONSTRAINT FK_EEECD5C2E79FF843');
        $this->addSql('ALTER TABLE household_invite DROP CONSTRAINT FK_EEECD5C27A512022');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT FK_EEECD5C2E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT FK_EEECD5C27A512022 FOREIGN KEY (invitee_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE registration');
        $this->addSql('ALTER TABLE household_invite DROP CONSTRAINT fk_eeecd5c2e79ff843');
        $this->addSql('ALTER TABLE household_invite DROP CONSTRAINT fk_eeecd5c27a512022');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT fk_eeecd5c2e79ff843 FOREIGN KEY (household_id) REFERENCES household (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT fk_eeecd5c27a512022 FOREIGN KEY (invitee_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
