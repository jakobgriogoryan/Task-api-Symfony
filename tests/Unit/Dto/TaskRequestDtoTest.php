<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\Task\CreateTaskRequest;
use App\Dto\Task\UpdateTaskRequest;
use App\Enum\TaskStatus;
use PHPUnit\Framework\TestCase;

final class TaskRequestDtoTest extends TestCase
{
    public function testCreateFromArrayMapsAllFields(): void
    {
        $dto = CreateTaskRequest::fromArray([
            'title' => 'Ship it',
            'description' => 'do the thing',
            'status' => 'in-progress',
            'due_date' => '2026-05-01',
            'assignee_id' => 7,
        ]);

        self::assertSame('Ship it', $dto->title);
        self::assertSame('do the thing', $dto->description);
        self::assertSame(TaskStatus::IN_PROGRESS, $dto->taskStatus());
        self::assertSame(7, $dto->assigneeId);
        self::assertSame('2026-05-01', $dto->dueDateObject()?->format('Y-m-d'));
    }

    public function testCreateFallsBackToTodoOnUnknownStatus(): void
    {
        $dto = CreateTaskRequest::fromArray(['title' => 'x', 'status' => 'bogus']);
        self::assertSame(TaskStatus::TODO, $dto->taskStatus());
    }

    public function testCreateIgnoresInvalidDate(): void
    {
        $dto = CreateTaskRequest::fromArray(['title' => 'x', 'due_date' => 'not-a-date']);
        self::assertNull($dto->dueDateObject());
    }

    public function testUpdateTrackingOnlyMarksTouchedFields(): void
    {
        $dto = UpdateTaskRequest::fromArray(['title' => 'new title']);

        self::assertArrayHasKey('title', $dto->touched);
        self::assertArrayNotHasKey('description', $dto->touched);
        self::assertArrayNotHasKey('status', $dto->touched);
        self::assertArrayNotHasKey('due_date', $dto->touched);
    }

    public function testUpdateClearsDueDateExplicitly(): void
    {
        $dto = UpdateTaskRequest::fromArray(['due_date' => null]);

        self::assertTrue($dto->clearDueDate);
        self::assertNull($dto->dueDateObject());
        self::assertArrayHasKey('due_date', $dto->touched);
    }

    public function testAllowedStatusesContainsAllEnumValues(): void
    {
        $values = CreateTaskRequest::allowedStatuses();
        foreach (TaskStatus::cases() as $case) {
            self::assertContains($case->value, $values);
        }
    }
}
