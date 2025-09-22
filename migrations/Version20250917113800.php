<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use AlexCrawford\LexoRank\Rank;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Webmozart\Assert\Assert;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250917113800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE todo ADD sort_rank VARCHAR(255) DEFAULT \'0\' NOT NULL');

        $checklistUuids = $this->getChecklistUuids();
        foreach ($checklistUuids as $checklistUuid) {
            Assert::string($checklistUuid);
            $uuidToRank = $this->getTodoUuidRankMapping($checklistUuid);
            foreach ($uuidToRank as $todoUuid => $rank) {
                $this->addSql(
                    'UPDATE todo SET sort_rank = ? WHERE uuid = ?',
                    [$rank, $todoUuid]
                );
            }
        }

        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_5a0eb6a0efefe67c');
        $this->addSql('ALTER TABLE todo DROP next_uuid');
    }

    private function getChecklistUuids(): array
    {
        $result = $this->connection->executeQuery('SELECT uuid FROM checklist');
        return $result->fetchFirstColumn();
    }

    /**
     * @return array<string, string> Mapping of todo UUID to its rank
     */
    private function getTodoUuidRankMapping(string $checklistUuid): array
    {
        $todos = $this->connection->executeQuery(
            'SELECT uuid, next_uuid FROM todo WHERE checklist_uuid = ?',
            [$checklistUuid]
        )->fetchAllAssociative();

        $uuidToRank = [];
        $nextUuid = null;
        $lastRank = Rank::forEmptySequence();
        while (!empty($todos)) {
            foreach ($todos as $index => $todo) {
                Assert::string($todo['uuid']);
                if ($todo['next_uuid'] === $nextUuid) {
                    $lastRank = Rank::before($lastRank);
                    $uuidToRank[$todo['uuid']] = $lastRank->get();
                    Assert::nullOrString($todo['next_uuid']);
                    $nextUuid = $todo['next_uuid'];
                    array_splice($todos, $index, 1);
                    break;
                }
            }
            $this->write('For checklist ' . $checklistUuid . ' could not establish full ordering. Start shuffle...');
            foreach ($todos as $index => $todo) {
                Assert::string($todo['uuid']);
                $lastRank = Rank::before($lastRank);
                $uuidToRank[$todo['uuid']] = $lastRank->get();
                array_splice($todos, $index, 1);
            }
        }

        return $uuidToRank;
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE todo ADD next_uuid VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE todo DROP sort_rank');
        $this->addSql('CREATE UNIQUE INDEX uniq_5a0eb6a0efefe67c ON todo (next_uuid)');
    }
}
