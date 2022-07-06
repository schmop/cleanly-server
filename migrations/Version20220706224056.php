<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220706224056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB25E79FF843');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT fk_527edb25e79ff843');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT fk_527edb25e79ff843 FOREIGN KEY (household_id) REFERENCES household (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
