<?php

declare(strict_types=1);

namespace App\Service\Project;

use App\Dto\Project\SaveProjectRequest;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProjectService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProjectRepository $projects,
    ) {
    }

    public function create(User $owner, SaveProjectRequest $dto): Project
    {
        $project = (new Project())
            ->setName($dto->name)
            ->setDescription($dto->description)
            ->setOwner($owner);

        $this->em->persist($project);
        $this->em->flush();

        return $project;
    }

    public function update(Project $project, SaveProjectRequest $dto): Project
    {
        $project->setName($dto->name)->setDescription($dto->description);
        $project->touch();
        $this->em->flush();

        return $project;
    }

    public function delete(Project $project): void
    {
        $this->em->remove($project);
        $this->em->flush();
    }

    public function addMember(Project $project, User $user): Project
    {
        if (!$project->isOwner($user)) {
            $project->addMember($user);
            $project->touch();
            $this->em->flush();
        }

        return $project;
    }

    public function removeMember(Project $project, User $user): Project
    {
        $project->removeMember($user);
        $project->touch();
        $this->em->flush();

        return $project;
    }

    /**
     * @return array{items: array<int, Project>, total: int, page: int, per_page: int}
     */
    public function listForUser(User $user, int $page, int $perPage, bool $seeAll = false): array
    {
        if ($seeAll) {
            return $this->projects->findAllPaginated($page, $perPage);
        }

        return $this->projects->findForUserPaginated($user, $page, $perPage);
    }
}
