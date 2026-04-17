<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Comment;
use App\Entity\User;
use App\Enum\UserRole;
use App\Security\Voter\CommentVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class CommentVoterTest extends TestCase
{
    public function testAuthorCanEditAndDeleteOwnComment(): void
    {
        $author = $this->user(1, UserRole::MEMBER);
        $comment = $this->comment(10, $author);

        $voter = new CommentVoter();
        $token = new UsernamePasswordToken($author, 'main', $author->getRoles());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $comment, [CommentVoter::EDIT]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $comment, [CommentVoter::DELETE]));
    }

    public function testOtherMemberCannotEdit(): void
    {
        $author = $this->user(1, UserRole::MEMBER);
        $other = $this->user(2, UserRole::MEMBER);
        $comment = $this->comment(10, $author);

        $voter = new CommentVoter();
        $token = new UsernamePasswordToken($other, 'main', $other->getRoles());

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $comment, [CommentVoter::EDIT]));
    }

    public function testAdminCanDeleteAnyComment(): void
    {
        $author = $this->user(1, UserRole::MEMBER);
        $admin = $this->user(2, UserRole::ADMIN);
        $comment = $this->comment(10, $author);

        $voter = new CommentVoter();
        $token = new UsernamePasswordToken($admin, 'main', $admin->getRoles());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $comment, [CommentVoter::DELETE]));
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

    private function comment(int $id, User $author): Comment
    {
        $comment = new Comment();
        $comment->setAuthor($author);
        $comment->setContent('hi');
        (new \ReflectionProperty(Comment::class, 'id'))->setValue($comment, $id);

        return $comment;
    }
}
