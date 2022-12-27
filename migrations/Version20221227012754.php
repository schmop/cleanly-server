<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Task\Entity\Task;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221227012754 extends AbstractMigration implements ContainerAwareInterface
{
    private ?ContainerInterface $container;
    public function setContainer(?ContainerInterface $container = null): void {
        $this->container = $container;
    }
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $entityManager = $this->container?->get('doctrine.orm.default_entity_manager');
        if (!($entityManager instanceof EntityManagerInterface)) {
            throw new \LogicException('Cannot migrate without entity manager!');
        }
        $entityManager->beginTransaction();
        $taskRepository = $entityManager->getRepository(Task::class);
        $tasks = $taskRepository->findAll();
        foreach ($tasks as $task) {
            $task->setIcon(
                match ($task->getIcon()) {
                    null => null,
                    'alarm' => 'alarm',
                    'barbell' => 'barbell',
                    'bicycle' => 'bike',
                    'book' => 'book',
                    'car' => 'car',
                    'checkmark' => 'check',
                    'construct' => 'hammer',
                    'cut' => 'scissors',
                    'dice' => 'dice',
                    'fitness' => 'heartbeat',
                    'film' => 'video',
                    'flag' => 'flag',
                    'flame' => 'candle',
                    'footsteps' => 'shoe',
                    'glasses' => 'sunglasses',
                    'headset' => 'headphones',
                    'heart' => 'heart',
                    'hourglass' => 'hourglass-empty',
                    'library' => 'books',
                    'moon' => 'moon',
                    'musicalNotes' => 'radio',
                    'newspaper' => 'news',
                    'nutrition' => 'apple',
                    'paw' => 'paw',
                    'person' => 'user',
                    'pint' => 'glass',
                    'restaurant' => 'tools-kitchen',
                    'rose' => 'flower',
                    'school' => 'school',
                    'shirt' => 'shirt',
                    'train' => 'train',
                    'wallet' => 'wallet',
                    'water' => 'droplet',
                    default => 'check',
                }
            );
        }
        $entityManager->flush();
        $entityManager->commit();
    }

    public function down(Schema $schema): void
    {
        // noop
    }
}
