<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\NotificationType;
use App\Event\CommentCreatedEvent;
use App\Event\TaskAssignedEvent;
use App\Event\TaskUpdatedEvent;
use App\Messenger\SendNotificationMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Observer that turns domain events into async notification jobs.
 *
 * Keeps notification concerns out of the service layer: services dispatch
 * "business happened" events and this subscriber decides who should be
 * notified, of what, through which message.
 */
class NotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TaskAssignedEvent::class => 'onTaskAssigned',
            TaskUpdatedEvent::class => 'onTaskUpdated',
            CommentCreatedEvent::class => 'onCommentCreated',
        ];
    }

    public function onTaskAssigned(TaskAssignedEvent $event): void
    {
        if ($event->assignee->getId() === $event->actor->getId()) {
            return;
        }

        $this->bus->dispatch(new SendNotificationMessage(
            (int) $event->assignee->getId(),
            NotificationType::TASK_ASSIGNED,
            [
                'task_id' => $event->task->getId(),
                'task_title' => $event->task->getTitle(),
                'project_id' => $event->task->getProject()?->getId(),
                'actor_id' => $event->actor->getId(),
            ],
        ));
    }

    public function onTaskUpdated(TaskUpdatedEvent $event): void
    {
        $assignee = $event->task->getAssignee();
        if ($assignee === null || $assignee->getId() === $event->actor->getId()) {
            return;
        }

        $type = \in_array('status', $event->changedFields, true)
            ? NotificationType::TASK_STATUS_CHANGED
            : NotificationType::TASK_UPDATED;

        $this->bus->dispatch(new SendNotificationMessage(
            (int) $assignee->getId(),
            $type,
            [
                'task_id' => $event->task->getId(),
                'task_title' => $event->task->getTitle(),
                'status' => $event->task->getStatus()->value,
                'changed' => $event->changedFields,
                'actor_id' => $event->actor->getId(),
            ],
        ));
    }

    public function onCommentCreated(CommentCreatedEvent $event): void
    {
        $task = $event->comment->getTask();
        if ($task === null) {
            return;
        }

        $assignee = $task->getAssignee();
        if ($assignee === null || $assignee->getId() === $event->actor->getId()) {
            return;
        }

        $this->bus->dispatch(new SendNotificationMessage(
            (int) $assignee->getId(),
            NotificationType::COMMENT_CREATED,
            [
                'task_id' => $task->getId(),
                'comment_id' => $event->comment->getId(),
                'actor_id' => $event->actor->getId(),
            ],
        ));
    }
}
