<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * RBAC matrix:
 * - admin    : all
 * - reviewer : VIEW any project
 * - member   : VIEW/EDIT/DELETE if owner; VIEW/MANAGE_MEMBERS if owner
 */
/**
 * @extends Voter<string, Project>
 */
final class ProjectVoter extends Voter
{
    public const VIEW = 'PROJECT_VIEW';
    public const EDIT = 'PROJECT_EDIT';
    public const DELETE = 'PROJECT_DELETE';
    public const MANAGE_MEMBERS = 'PROJECT_MANAGE_MEMBERS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Project && \in_array(
            $attribute,
            [self::VIEW, self::EDIT, self::DELETE, self::MANAGE_MEMBERS],
            true,
        );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Project) {
            return false;
        }

        $role = $user->getSelectedRole();

        if ($role === UserRole::ADMIN) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user, $role),
            self::EDIT, self::DELETE, self::MANAGE_MEMBERS => $subject->isOwner($user),
            default => false,
        };
    }

    private function canView(Project $project, User $user, UserRole $role): bool
    {
        if ($role === UserRole::REVIEWER) {
            return true;
        }

        return $project->isOwner($user) || $project->hasMember($user);
    }
}
