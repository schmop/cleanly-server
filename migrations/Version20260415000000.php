<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert task duration from days to hours';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE task SET duration = duration * 24 WHERE duration IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE task SET duration = duration / 24 WHERE duration IS NOT NULL');
    }
}
