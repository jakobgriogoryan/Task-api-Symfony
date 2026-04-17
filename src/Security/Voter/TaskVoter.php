<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * - admin    : all
 * - reviewer : VIEW, STATUS (change status only), COMMENT (implied by TASK_COMMENT)
 * - member   : VIEW/EDIT/DELETE/ASSIGN if project owner; VIEW/EDIT status if assignee or project member.
 */
/**
 * @extends Voter<string, Task>
 */
final class TaskVoter extends Voter
{
    public const VIEW = 'TASK_VIEW';
    public const EDIT = 'TASK_EDIT';
    public const DELETE = 'TASK_DELETE';
    public const ASSIGN = 'TASK_ASSIGN';
    public const COMMENT = 'TASK_COMMENT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Task && \in_array(
            $attribute,
            [self::VIEW, self::EDIT, self::DELETE, self::ASSIGN, self::COMMENT],
            true,
        );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Task) {
            return false;
        }

        $role = $user->getSelectedRole();
        if ($role === UserRole::ADMIN) {
            return true;
        }

        $project = $subject->getProject();
        if ($project === null) {
            return false;
        }

        $isOwner = $project->isOwner($user);
        $isMember = $project->hasMember($user);
        $isAssignee = $subject->getAssignee()?->getId() === $user->getId();
        $isCreator = $subject->getCreator()?->getId() === $user->getId();

        return match ($attribute) {
            self::VIEW => $role === UserRole::REVIEWER || $isOwner || $isMember || $isAssignee || $isCreator,
            self::COMMENT => $role === UserRole::REVIEWER || $isOwner || $isMember || $isAssignee || $isCreator,
            self::EDIT => $isOwner || $isAssignee || $isCreator,
            self::DELETE => $isOwner || $isCreator,
            self::ASSIGN => $isOwner,
            default => false,
        };
    }
}
