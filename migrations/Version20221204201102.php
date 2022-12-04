<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221204201102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE household_privilege (level INT NOT NULL, household INT NOT NULL, "user" INT NOT NULL, PRIMARY KEY(household, "user"))');
        $this->addSql('CREATE INDEX IDX_CCF8F53954C32FC0 ON household_privilege (household)');
        $this->addSql('CREATE INDEX IDX_CCF8F539356B3608 ON household_privilege ("user")');
        $this->addSql('ALTER TABLE household_privilege ADD CONSTRAINT FK_CCF8F53954C32FC0 FOREIGN KEY (household) REFERENCES household (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE household_privilege ADD CONSTRAINT FK_CCF8F539356B3608 FOREIGN KEY ("user") REFERENCES "user" ("id") NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE household DROP CONSTRAINT fk_54c32fc0642b8210');
        $this->addSql('DROP INDEX idx_54c32fc0642b8210');
        $this->addSql('ALTER TABLE household_invite ADD inviter_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT FK_EEECD5C2B79F4F04 FOREIGN KEY (inviter_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EEECD5C2B79F4F04 ON household_invite (inviter_id)');
        $this->addSql('UPDATE household_invite SET inviter_id = household.admin_id FROM household WHERE household_invite.household_id = household.id AND household.admin_id IS NOT NULL');
        $this->addSql('INSERT INTO household_privilege SELECT 2 AS level, id AS household, admin_id AS "user" FROM household where admin_id IS NOT NULL');
        $this->addSql('ALTER TABLE household DROP admin_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE household_privilege DROP CONSTRAINT FK_CCF8F53954C32FC0');
        $this->addSql('ALTER TABLE household_privilege DROP CONSTRAINT FK_CCF8F539356B3608');
        $this->addSql('DROP TABLE household_privilege');
        $this->addSql('ALTER TABLE household_invite DROP CONSTRAINT FK_EEECD5C2B79F4F04');
        $this->addSql('DROP INDEX IDX_EEECD5C2B79F4F04');
        $this->addSql('ALTER TABLE household_invite DROP inviter_id');
        $this->addSql('ALTER TABLE household ADD admin_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE household ADD CONSTRAINT fk_54c32fc0642b8210 FOREIGN KEY (admin_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_54c32fc0642b8210 ON household (admin_id)');
    }
}
