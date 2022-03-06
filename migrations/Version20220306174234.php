<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220306174234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE household_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE task_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE user_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE household (id INT NOT NULL, admin_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, picture VARCHAR(255) DEFAULT NULL, color VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_54C32FC0642B8210 ON household (admin_id)');
        $this->addSql('CREATE TABLE household_members (household_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(household_id, user_id))');
        $this->addSql('CREATE INDEX IDX_285438D1E79FF843 ON household_members (household_id)');
        $this->addSql('CREATE INDEX IDX_285438D1A76ED395 ON household_members (user_id)');
        $this->addSql('CREATE TABLE household_invite (token VARCHAR(255) NOT NULL, household_id INT DEFAULT NULL, valid_until TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(token))');
        $this->addSql('CREATE INDEX IDX_EEECD5C2E79FF843 ON household_invite (household_id)');
        $this->addSql('COMMENT ON COLUMN household_invite.valid_until IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE task (id INT NOT NULL, household_id INT DEFAULT NULL, last_completed TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, duration INT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(510) DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_527EDB25E79FF843 ON task (household_id)');
        $this->addSql('COMMENT ON COLUMN task.last_completed IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "user" (id INT NOT NULL, email VARCHAR(180) NOT NULL, name VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('ALTER TABLE household ADD CONSTRAINT FK_54C32FC0642B8210 FOREIGN KEY (admin_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE household_members ADD CONSTRAINT FK_285438D1E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE household_members ADD CONSTRAINT FK_285438D1A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE household_invite ADD CONSTRAINT FK_EEECD5C2E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE household_members DROP CONSTRAINT FK_285438D1E79FF843');
        $this->addSql('ALTER TABLE household_invite DROP CONSTRAINT FK_EEECD5C2E79FF843');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB25E79FF843');
        $this->addSql('ALTER TABLE household DROP CONSTRAINT FK_54C32FC0642B8210');
        $this->addSql('ALTER TABLE household_members DROP CONSTRAINT FK_285438D1A76ED395');
        $this->addSql('DROP SEQUENCE household_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE task_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE user_id_seq CASCADE');
        $this->addSql('DROP TABLE household');
        $this->addSql('DROP TABLE household_members');
        $this->addSql('DROP TABLE household_invite');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE "user"');
    }
}
