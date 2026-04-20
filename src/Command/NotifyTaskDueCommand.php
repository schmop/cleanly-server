<?php

namespace App\Command;

use App\Household\HouseholdRepository;
use App\Push\Pusher;
use App\Task\Entity\Task;
use App\Task\TaskSecretary;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Lambdish\Phunctional\filter;
use function Lambdish\Phunctional\map;

#[AsCommand('cleanly:notify-task-due')]
class NotifyTaskDueCommand extends Command
{
    public function __construct(
        private readonly Pusher              $pusher,
        private readonly HouseholdRepository $householdRepository,
        private readonly TaskSecretary       $taskSecretary,
        private readonly LoggerInterface     $logger,
    ) {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Just returns the devices and push notifications, but does not send any push notifications!');
    }

    /**
     * @throws \DivisionByZeroError
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $households = $this->householdRepository->findAll();
            foreach ($households as $household) {
                $dueTasks = filter(
                    fn(Task $task) => $task->getReminderConfig() !== null
                        ? $this->taskSecretary->isReminderDue($task)
                        : $this->taskSecretary->isTaskDue($task) && !$this->taskSecretary->wasAlreadyNotified($task),
                    $household->getTasks(),
                );
                if (empty($dueTasks)) {
                    continue;
                }
                if ($input->getOption('dry-run')) {
                    dump(map(
                        fn(Task $task) => [
                            'household' => $task->getHousehold()->getName(),
                            'task' => $task->jsonSerialize()
                        ],
                        $dueTasks
                    ));

                    continue;
                }
                foreach ($dueTasks as $task) {
                    $this->pusher->publishTaskDue($task);
                    $this->taskSecretary->markTaskAsPushed($task);
                }
                $output->writeln(sprintf('Sent %d push notifications in %s', count($dueTasks), $household->getName()));
            }
        } catch (\Exception $e) {
            $this->logger->error('Could not notify task as due, {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            $output->writeln(sprintf(
                '[ERROR]: %s, callstack:\n %s',
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }
        return Command::SUCCESS;
    }
}
