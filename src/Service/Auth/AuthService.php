<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function emailExists(string $email): bool
    {
        return $this->users->findByEmail($email) !== null;
    }

    public function register(string $email, string $rawPassword, string $name, UserRole $role): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setName($name)
            ->setSelectedRole($role);

        $user->setPassword($this->passwordHasher->hashPassword($user, $rawPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
