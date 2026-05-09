<?php

declare(strict_types=1);

namespace App\Tests\Task;

use App\Household\Entity\Household;
use App\Household\ReassignmentStrategy;
use App\Push\Pusher;
use App\Task\Entity\Task;
use App\Task\Exception\TaskAssignException;
use App\Task\TaskAssigner;
use App\Task\TaskLogRepository;
use App\Task\TaskPublisher;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TaskAssignerTest extends TestCase
{
    private Pusher $pusher;
    private TaskPublisher $publisher;
    private TaskLogRepository $taskLogRepository;
    private EntityManagerInterface $em;
    private TaskAssigner $assigner;

    protected function setUp(): void
    {
        $this->pusher = $this->createMock(Pusher::class);
        $this->publisher = $this->createMock(TaskPublisher::class);
        $this->taskLogRepository = $this->createMock(TaskLogRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->assigner = new TaskAssigner(
            $this->pusher,
            $this->publisher,
            $this->taskLogRepository,
            $this->em,
        );
    }

    public function testAssignToMemberPersistsAndPublishesAndPushes(): void
    {
        $member = $this->makeUser(1);
        $task = $this->makeTask([$member]);

        $this->publisher->expects($this->once())->method('publish')->with($task->getHousehold());
        $this->pusher->expects($this->once())->method('publishTaskAssign')->with($task, $member);
        $this->em->expects($this->once())->method('flush');

        $this->assigner->assignTo($task, $member);
        $this->assertSame($member, $task->getAssignee());
    }

    public function testAssignToNullPublishesButDoesNotPush(): void
    {
        $member = $this->makeUser(1);
        $task = $this->makeTask([$member]);
        $task->assignTo($member);

        $this->publisher->expects($this->once())->method('publish');
        $this->pusher->expects($this->never())->method('publishTaskAssign');
        $this->em->expects($this->once())->method('flush');

        $this->assigner->assignTo($task, null);
        $this->assertNull($task->getAssignee());
    }

    public function testAssignToNonMemberThrows(): void
    {
        $member = $this->makeUser(1);
        $stranger = $this->makeUser(99);
        $task = $this->makeTask([$member]);

        $this->publisher->expects($this->never())->method('publish');
        $this->pusher->expects($this->never())->method('publishTaskAssign');
        $this->em->expects($this->never())->method('flush');

        $this->expectException(TaskAssignException::class);
        $this->assigner->assignTo($task, $stranger);
    }

    public function testAutoAssignNoOpsWhenActiveUserIsNotTheAssignee(): void
    {
        $assignee = $this->makeUser(1);
        $other = $this->makeUser(2);
        $task = $this->makeTask([$assignee, $other], strategy: ReassignmentStrategy::Rotate);
        $task->assignTo($assignee);

        $this->publisher->expects($this->never())->method('publish');
        $this->em->expects($this->never())->method('flush');
        $this->taskLogRepository->expects($this->never())->method('getNextAssignmentRotation');

        $this->assigner->autoAssign($task, $other);
        $this->assertSame($assignee, $task->getAssignee());
    }

    public function testAutoAssignNoneStrategyKeepsAssignee(): void
    {
        $assignee = $this->makeUser(1);
        $task = $this->makeTask([$assignee], strategy: ReassignmentStrategy::None);
        $task->assignTo($assignee);

        $this->publisher->expects($this->never())->method('publish');
        $this->em->expects($this->never())->method('flush');

        $this->assigner->autoAssign($task, $assignee);
        $this->assertSame($assignee, $task->getAssignee());
    }

    public function testAutoAssignUnassignClearsAssignee(): void
    {
        $assignee = $this->makeUser(1);
        $task = $this->makeTask([$assignee], strategy: ReassignmentStrategy::Unassign);
        $task->assignTo($assignee);

        $this->publisher->expects($this->once())->method('publish');
        $this->em->expects($this->once())->method('flush');

        $this->assigner->autoAssign($task, $assignee);
        $this->assertNull($task->getAssignee());
    }

    public function testAutoAssignRotateUsesRepositoryNextRotation(): void
    {
        $current = $this->makeUser(1);
        $next = $this->makeUser(2);
        $task = $this->makeTask([$current, $next], strategy: ReassignmentStrategy::Rotate);
        $task->assignTo($current);

        $this->taskLogRepository
            ->expects($this->once())
            ->method('getNextAssignmentRotation')
            ->with($task)
            ->willReturn($next);

        $this->publisher->expects($this->once())->method('publish');
        $this->pusher->expects($this->once())->method('publishTaskAssign')->with($task, $next);
        $this->em->expects($this->once())->method('flush');

        $this->assigner->autoAssign($task, $current);
        $this->assertSame($next, $task->getAssignee());
    }

    public function testAutoAssignRotateToNullDoesNotPush(): void
    {
        $current = $this->makeUser(1);
        $task = $this->makeTask([$current], strategy: ReassignmentStrategy::Rotate);
        $task->assignTo($current);

        $this->taskLogRepository->method('getNextAssignmentRotation')->willReturn(null);

        $this->pusher->expects($this->never())->method('publishTaskAssign');
        $this->publisher->expects($this->once())->method('publish');

        $this->assigner->autoAssign($task, $current);
        $this->assertNull($task->getAssignee());
    }

    /**
     * @param User[] $members
     */
    private function makeTask(array $members, ReassignmentStrategy $strategy = ReassignmentStrategy::None): Task
    {
        $household = $this->createMock(Household::class);
        $household->method('getMembers')->willReturn(new ArrayCollection($members));
        $household->method('getReassignmentStrategy')->willReturn($strategy);

        $task = new Task();
        $task->setHousehold($household);
        $task->setName('test');
        $task->setStars(0);

        return $task;
    }

    private function makeUser(int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        return $user;
    }
}
