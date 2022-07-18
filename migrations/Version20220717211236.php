<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Household;
use App\Todo\Entity\Todo;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use App\Todo\TodoRepository;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220717211236 extends AbstractMigration implements ContainerAwareInterface
{
    private ContainerInterface $container;
    public function setContainer(ContainerInterface $container = null) {
        $this->container = $container;
    }
    
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $entityManager = $this->container->get('doctrine.orm.default_entity_manager');
        $entityManager->beginTransaction();
        try {
            $householdRepository = $entityManager->getRepository(Household::class);
            /** @var TodoRepository $todoRepository */
            $todoRepository = $entityManager->getRepository(Todo::class);
            $households = $householdRepository->findAll();
            foreach ($households as $household) {
                $checklist = $household->getChecklist();
                $lastTodo = null;
                foreach ($checklist as $todo) {
                    // just add somehow
                    $todo->setNext($lastTodo?->getUuid());
                    $lastTodo = $todo;
                    $entityManager->flush();
                    echo "added " . $todo->getUuid() . PHP_EOL;
                }
                echo sprintf('Migrated household %s (%d)', $household->getName(), $household->getId()) . PHP_EOL;
            }
        } catch (\Throwable $someError) {
            $entityManager->rollback();
            echo sprintf('Could not migrate checklist ordering, reason: %s', $someError->getMessage());
            throw $someError;
        }
        $entityManager->commit();
    }

    public function down(Schema $schema): void
    {
        // don't do anything
    }
}
