<?php

declare(strict_types=1);

namespace App\Dto\Project;

use App\Entity\Project;

final class ProjectResource
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Project $project): array
    {
        return [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'owner_id' => $project->getOwner()?->getId(),
            'member_ids' => array_values(array_map(
                static fn ($m) => $m->getId(),
                $project->getMembers()->toArray(),
            )),
            'created_at' => $project->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $project->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<int, Project> $projects
     *
     * @return array<int, array<string, mixed>>
     */
    public static function collection(array $projects): array
    {
        return array_map([self::class, 'from'], $projects);
    }
}
