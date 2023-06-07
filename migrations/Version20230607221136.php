<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230607221136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE checklist (uuid VARCHAR(255) NOT NULL, household_id INT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX IDX_5C696D2FE79FF843 ON checklist (household_id)');
        $this->addSql('ALTER TABLE checklist ADD CONSTRAINT FK_5C696D2FE79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE todo DROP CONSTRAINT fk_5a0eb6a0e79ff843');
        $this->addSql('DROP INDEX idx_5a0eb6a0e79ff843');
        $this->addSql('INSERT INTO checklist (uuid, household_id, name) SELECT gen_random_uuid(), household.id, \'Default\' FROM household');
        $this->addSql('ALTER TABLE todo ADD checklist_uuid VARCHAR(255)');
        $this->addSql('UPDATE todo SET checklist_uuid = checklist.uuid FROM checklist WHERE todo.household_id = checklist.household_id');
        $this->addSql('ALTER TABLE todo ALTER COLUMN checklist_uuid SET NOT NULL');
        $this->addSql('ALTER TABLE todo DROP household_id');
        $this->addSql('ALTER TABLE todo ADD CONSTRAINT FK_5A0EB6A0578F5541 FOREIGN KEY (checklist_uuid) REFERENCES checklist (uuid) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_5A0EB6A0578F5541 ON todo (checklist_uuid)');
    }

    public function down(Schema $schema): void
    {
        throw new \LogicException('Downgrade migration not supported!');
    }
}
