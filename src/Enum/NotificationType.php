<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case TASK_ASSIGNED = 'task.assigned';
    case TASK_UPDATED = 'task.updated';
    case TASK_STATUS_CHANGED = 'task.status_changed';
    case COMMENT_CREATED = 'comment.created';
    case PROJECT_MEMBER_ADDED = 'project.member_added';
}
