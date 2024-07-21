<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 *
 */
final class Version20240721181427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklist ALTER COLUMN sort_rank SET DATA TYPE VARCHAR(255) COLLATE "C"');
    }

    public function down(Schema $schema): void
    {
    }
}
