<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Concerns\ApiJsonResponse;
use App\Dto\Task\AssignTaskRequest;
use App\Dto\Task\CreateTaskRequest;
use App\Dto\Task\TaskResource;
use App\Dto\Task\UpdateTaskRequest;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Search\TaskSearchInterface;
use App\Security\Voter\ProjectVoter;
use App\Security\Voter\TaskVoter;
use App\Service\Task\TaskService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_tasks_')]
#[OA\Tag(name: 'Tasks')]
class TaskController extends AbstractController
{
    use ApiJsonResponse;

    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskRepository $taskRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly ValidatorInterface $validator,
        private readonly TaskSearchInterface $search,
    ) {
    }

    #[Route('/projects/{projectId}/tasks', name: 'index', methods: ['GET'], requirements: ['projectId' => '\d+'])]
    #[OA\Get(summary: 'List tasks in a project (cached, filterable)')]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'due_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'due_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'assignee', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer'))]
    public function index(int $projectId, Request $request): JsonResponse
    {
        $project = $this->loadProject($projectId);
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        $filters = [
            'status' => $request->query->get('status'),
            'due_from' => $request->query->get('due_from'),
            'due_to' => $request->query->get('due_to'),
            'q' => $request->query->get('q'),
            'assignee' => $request->query->get('assignee') !== null
                ? (int) $request->query->get('assignee')
                : null,
            'sort' => $request->query->get('sort'),
            'page' => (int) $request->query->get('page', 1),
            'per_page' => (int) $request->query->get('per_page', 15),
        ];

        $result = $this->tasks->listForProject($project, $filters);

        return $this->success([
            'items' => $result['items'],
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
            ],
        ]);
    }

    #[Route(
        '/projects/{projectId}/tasks',
        name: 'store',
        methods: ['POST'],
        requirements: ['projectId' => '\d+'],
    )]
    public function store(int $projectId, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $project = $this->loadProject($projectId);
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $dto = CreateTaskRequest::fromArray($payload);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $task = $this->tasks->create($project, $user, $dto);

        return $this->created(TaskResource::from($task));
    }

    #[Route('/tasks/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $task = $this->loadTask($id);
        $this->denyAccessUnlessGranted(TaskVoter::VIEW, $task);

        return $this->success(TaskResource::from($task));
    }

    #[Route('/tasks/{id}', name: 'update', methods: ['PATCH', 'PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $task = $this->loadTask($id);
        $this->denyAccessUnlessGranted(TaskVoter::EDIT, $task);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $dto = UpdateTaskRequest::fromArray($payload);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $task = $this->tasks->update($task, $user, $dto);

        return $this->success(TaskResource::from($task));
    }

    #[Route('/tasks/{id}', name: 'destroy', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function destroy(int $id): JsonResponse
    {
        $task = $this->loadTask($id);
        $this->denyAccessUnlessGranted(TaskVoter::DELETE, $task);

        $this->tasks->delete($task);

        return $this->noContent();
    }

    #[Route('/tasks/{id}/assign', name: 'assign', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function assign(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $task = $this->loadTask($id);
        $this->denyAccessUnlessGranted(TaskVoter::ASSIGN, $task);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $dto = AssignTaskRequest::fromArray($payload);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $task = $this->tasks->assign($task, $user, $dto->assigneeId);

        return $this->success(TaskResource::from($task));
    }

    #[Route('/tasks/search', name: 'search', methods: ['GET'])]
    #[OA\Get(summary: 'Global task full-text search')]
    #[OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer'))]
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', 15)));

        if ($query === '') {
            return $this->success([
                'items' => [],
                'meta' => ['total' => 0, 'page' => $page, 'per_page' => $perPage],
            ]);
        }

        $hits = $this->search->search($query, $page, $perPage);

        $items = TaskResource::collection($this->taskRepository->findByIdsInOrder($hits['ids']));

        return $this->success([
            'items' => $items,
            'meta' => [
                'total' => $hits['total'],
                'page' => $page,
                'per_page' => $perPage,
                'driver' => $hits['driver'],
            ],
        ]);
    }

    private function loadProject(int $id): Project
    {
        $project = $this->projectRepository->find($id);
        if ($project === null) {
            throw $this->createNotFoundException('Project not found.');
        }

        return $project;
    }

    private function loadTask(int $id): Task
    {
        $task = $this->taskRepository->find($id);
        if ($task === null) {
            throw $this->createNotFoundException('Task not found.');
        }

        return $task;
    }
}
