<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account_deletion_request table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE account_deletion_request (uuid VARCHAR(255) NOT NULL, token VARCHAR(255) NOT NULL, user_id INT NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX IDX_account_deletion_request_user ON account_deletion_request (user_id)');
        $this->addSql('ALTER TABLE account_deletion_request ADD CONSTRAINT FK_account_deletion_request_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE account_deletion_request');
    }
}
