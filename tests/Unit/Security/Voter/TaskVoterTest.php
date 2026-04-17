<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\UserRole;
use App\Security\Voter\TaskVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class TaskVoterTest extends TestCase
{
    public function testOwnerCanAssignAndDelete(): void
    {
        $owner = $this->user(1, UserRole::MEMBER);
        $project = $this->project(10, $owner);
        $task = $this->task(100, $project, $owner);

        $voter = new TaskVoter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($owner), $task, [TaskVoter::ASSIGN]),
        );
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($owner), $task, [TaskVoter::DELETE]),
        );
    }

    public function testAssigneeCanEditButNotAssign(): void
    {
        $owner = $this->user(1, UserRole::MEMBER);
        $assignee = $this->user(2, UserRole::MEMBER);
        $project = $this->project(10, $owner);
        $task = $this->task(100, $project, $owner);
        $task->setAssignee($assignee);

        $voter = new TaskVoter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($assignee), $task, [TaskVoter::EDIT]),
        );
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($assignee), $task, [TaskVoter::ASSIGN]),
        );
    }

    public function testReviewerCanViewAndComment(): void
    {
        $owner = $this->user(1, UserRole::MEMBER);
        $reviewer = $this->user(2, UserRole::REVIEWER);
        $project = $this->project(10, $owner);
        $task = $this->task(100, $project, $owner);

        $voter = new TaskVoter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($reviewer), $task, [TaskVoter::VIEW]),
        );
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($reviewer), $task, [TaskVoter::COMMENT]),
        );
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($reviewer), $task, [TaskVoter::DELETE]),
        );
    }

    public function testOutsiderIsDenied(): void
    {
        $owner = $this->user(1, UserRole::MEMBER);
        $outsider = $this->user(9, UserRole::MEMBER);
        $project = $this->project(10, $owner);
        $task = $this->task(100, $project, $owner);

        $voter = new TaskVoter();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($outsider), $task, [TaskVoter::VIEW]),
        );
    }

    private function user(int $id, UserRole $role): User
    {
        $user = new User();
        $user->setEmail(sprintf('u%d@example.com', $id));
        $user->setName('u'.$id);
        $user->setSelectedRole($role);
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    private function project(int $id, User $owner): Project
    {
        $project = new Project();
        $project->setName('p'.$id);
        $project->setOwner($owner);
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, $id);

        return $project;
    }

    private function task(int $id, Project $project, User $creator): Task
    {
        $task = new Task();
        $task->setProject($project);
        $task->setCreator($creator);
        $task->setTitle('t');
        (new \ReflectionProperty(Task::class, 'id'))->setValue($task, $id);

        return $task;
    }

    private function tokenFor(User $user): TokenInterface
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}
