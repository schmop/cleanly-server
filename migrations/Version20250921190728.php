<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Exception\NotImplemented;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250921190728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE todo ALTER COLUMN sort_rank TYPE VARCHAR(255) COLLATE "C"');
    }

    public function down(Schema $schema): void
    {
        throw new NotImplemented('Not implemented.');
    }
}
