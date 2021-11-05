<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211105191545 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE household_members (household_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_285438D1E79FF843 (household_id), INDEX IDX_285438D1A76ED395 (user_id), PRIMARY KEY(household_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE household_members ADD CONSTRAINT FK_285438D1E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE household_members ADD CONSTRAINT FK_285438D1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE household ADD admin_id INT DEFAULT NULL, DROP admin');
        $this->addSql('ALTER TABLE household ADD CONSTRAINT FK_54C32FC0642B8210 FOREIGN KEY (admin_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_54C32FC0642B8210 ON household (admin_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE household_members');
        $this->addSql('ALTER TABLE household DROP FOREIGN KEY FK_54C32FC0642B8210');
        $this->addSql('DROP INDEX IDX_54C32FC0642B8210 ON household');
        $this->addSql('ALTER TABLE household ADD admin VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, DROP admin_id');
    }
}
