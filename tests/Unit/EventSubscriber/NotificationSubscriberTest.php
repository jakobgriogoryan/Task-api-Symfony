<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\UserRole;
use App\Event\TaskAssignedEvent;
use App\Event\TaskUpdatedEvent;
use App\EventSubscriber\NotificationSubscriber;
use App\Messenger\SendNotificationMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class NotificationSubscriberTest extends TestCase
{
    public function testOnTaskAssignedDispatchesMessage(): void
    {
        $assignee = $this->user(2, UserRole::MEMBER);
        $actor = $this->user(1, UserRole::MEMBER);
        $task = $this->task(10, $actor);
        $task->setAssignee($assignee);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (SendNotificationMessage $m): Envelope {
                self::assertSame(2, $m->recipientId);
                self::assertSame(NotificationType::TASK_ASSIGNED, $m->type);

                return new Envelope($m);
            });

        (new NotificationSubscriber($bus))
            ->onTaskAssigned(new TaskAssignedEvent($task, $assignee, $actor));
    }

    public function testOnTaskAssignedSkipsSelfAssignment(): void
    {
        $actor = $this->user(1, UserRole::MEMBER);
        $task = $this->task(10, $actor);
        $task->setAssignee($actor);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        (new NotificationSubscriber($bus))
            ->onTaskAssigned(new TaskAssignedEvent($task, $actor, $actor));
    }

    public function testOnTaskUpdatedChoosesStatusTypeWhenStatusChanged(): void
    {
        $assignee = $this->user(2, UserRole::MEMBER);
        $actor = $this->user(1, UserRole::MEMBER);
        $task = $this->task(10, $actor);
        $task->setAssignee($assignee);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (SendNotificationMessage $m): Envelope {
                self::assertSame(NotificationType::TASK_STATUS_CHANGED, $m->type);

                return new Envelope($m);
            });

        (new NotificationSubscriber($bus))
            ->onTaskUpdated(new TaskUpdatedEvent($task, $actor, ['status']));
    }

    public function testOnTaskUpdatedSkipsWhenNoAssignee(): void
    {
        $actor = $this->user(1, UserRole::MEMBER);
        $task = $this->task(10, $actor);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        (new NotificationSubscriber($bus))
            ->onTaskUpdated(new TaskUpdatedEvent($task, $actor, ['title']));
    }

    public function testSubscribesToAllThreeEvents(): void
    {
        $events = NotificationSubscriber::getSubscribedEvents();
        self::assertCount(3, $events);
        self::assertArrayHasKey(TaskAssignedEvent::class, $events);
        self::assertArrayHasKey(TaskUpdatedEvent::class, $events);
    }

    private function user(int $id, UserRole $role): User
    {
        $user = new User();
        $user->setEmail("u$id@example.com");
        $user->setName("u$id");
        $user->setSelectedRole($role);
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    private function task(int $id, User $creator): Task
    {
        $project = new Project();
        $project->setName('p');
        $project->setOwner($creator);
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, 1);

        $task = new Task();
        $task->setProject($project);
        $task->setCreator($creator);
        $task->setTitle('t');
        (new \ReflectionProperty(Task::class, 'id'))->setValue($task, $id);

        return $task;
    }
}
