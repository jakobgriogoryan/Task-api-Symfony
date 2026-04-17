<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\UserRole;
use App\Security\Voter\ProjectVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ProjectVoterTest extends TestCase
{
    public function testAdminCanDoAnything(): void
    {
        $voter = new ProjectVoter();
        $admin = $this->user(1, UserRole::ADMIN);
        $project = $this->project(99, $this->user(2, UserRole::MEMBER));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($admin), $project, [ProjectVoter::EDIT]),
        );
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($admin), $project, [ProjectVoter::DELETE]),
        );
    }

    public function testReviewerCanView(): void
    {
        $voter = new ProjectVoter();
        $reviewer = $this->user(1, UserRole::REVIEWER);
        $project = $this->project(99, $this->user(2, UserRole::MEMBER));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($reviewer), $project, [ProjectVoter::VIEW]),
        );
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($reviewer), $project, [ProjectVoter::EDIT]),
        );
    }

    public function testMemberNeedsOwnershipToEdit(): void
    {
        $voter = new ProjectVoter();
        $me = $this->user(1, UserRole::MEMBER);
        $someoneElse = $this->user(2, UserRole::MEMBER);
        $mine = $this->project(10, $me);
        $theirs = $this->project(11, $someoneElse);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($me), $mine, [ProjectVoter::EDIT]),
        );
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($me), $theirs, [ProjectVoter::EDIT]),
        );
    }

    public function testMemberCanViewProjectTheyBelongTo(): void
    {
        $voter = new ProjectVoter();
        $me = $this->user(1, UserRole::MEMBER);
        $owner = $this->user(2, UserRole::MEMBER);
        $project = $this->project(10, $owner);
        $project->addMember($me);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($me), $project, [ProjectVoter::VIEW]),
        );
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($me), $project, [ProjectVoter::EDIT]),
        );
    }

    private function user(int $id, UserRole $role): User
    {
        $user = new User();
        $user->setEmail(sprintf('user%d@example.com', $id));
        $user->setName('u'.$id);
        $user->setSelectedRole($role);

        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }

    private function project(int $id, User $owner): Project
    {
        $project = new Project();
        $project->setName('p'.$id);
        $project->setOwner($owner);

        $ref = new \ReflectionProperty(Project::class, 'id');
        $ref->setValue($project, $id);

        return $project;
    }

    private function tokenFor(User $user): TokenInterface
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}
