<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251202165910 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE transaction (uuid VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, amount INT NOT NULL, transaction_type VARCHAR(255) DEFAULT \'expense\' NOT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id INT NOT NULL, household_id INT NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('CREATE INDEX IDX_723705D1A76ED395 ON transaction (user_id)');
        $this->addSql('CREATE INDEX IDX_723705D1E79FF843 ON transaction (household_id)');
        $this->addSql('CREATE TABLE transaction_share (uuid VARCHAR(255) NOT NULL, share INT NOT NULL, user_id INT NOT NULL, transaction_uuid VARCHAR NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('CREATE INDEX IDX_4E354D1AA76ED395 ON transaction_share (user_id)');
        $this->addSql('CREATE INDEX IDX_4E354D1A333C6E07 ON transaction_share (transaction_uuid)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE transaction_share ADD CONSTRAINT FK_4E354D1AA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE transaction_share ADD CONSTRAINT FK_4E354D1A333C6E07 FOREIGN KEY (transaction_uuid) REFERENCES transaction (uuid) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transaction DROP CONSTRAINT FK_723705D1A76ED395');
        $this->addSql('ALTER TABLE transaction DROP CONSTRAINT FK_723705D1E79FF843');
        $this->addSql('ALTER TABLE transaction_share DROP CONSTRAINT FK_4E354D1AA76ED395');
        $this->addSql('ALTER TABLE transaction_share DROP CONSTRAINT FK_4E354D1A333C6E07');
        $this->addSql('DROP TABLE transaction');
        $this->addSql('DROP TABLE transaction_share');
    }
}
