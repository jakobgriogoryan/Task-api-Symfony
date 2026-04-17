<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Concerns\ApiJsonResponse;
use App\Dto\Comment\CommentResource;
use App\Dto\Comment\SaveCommentRequest;
use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\TaskRepository;
use App\Security\Voter\CommentVoter;
use App\Security\Voter\TaskVoter;
use App\Service\Comment\CommentService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_comments_')]
#[OA\Tag(name: 'Comments')]
class CommentController extends AbstractController
{
    use ApiJsonResponse;

    public function __construct(
        private readonly CommentService $commentsService,
        private readonly CommentRepository $commentRepository,
        private readonly TaskRepository $taskRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/tasks/{taskId}/comments', name: 'index', methods: ['GET'], requirements: ['taskId' => '\d+'])]
    public function index(int $taskId, Request $request): JsonResponse
    {
        $task = $this->loadTask($taskId);
        $this->denyAccessUnlessGranted(TaskVoter::VIEW, $task);

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', 20)));

        $result = $this->commentsService->listForTask($task, $page, $perPage);

        return $this->success([
            'items' => CommentResource::collection($result['items']),
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
            ],
        ]);
    }

    #[Route('/tasks/{taskId}/comments', name: 'store', methods: ['POST'], requirements: ['taskId' => '\d+'])]
    public function store(int $taskId, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $task = $this->loadTask($taskId);
        $this->denyAccessUnlessGranted(TaskVoter::COMMENT, $task);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $dto = SaveCommentRequest::fromArray($payload);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $comment = $this->commentsService->create($task, $user, $dto);

        return $this->created(CommentResource::from($comment));
    }

    #[Route('/comments/{id}', name: 'update', methods: ['PATCH', 'PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $comment = $this->loadComment($id);
        $this->denyAccessUnlessGranted(CommentVoter::EDIT, $comment);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $dto = SaveCommentRequest::fromArray($payload);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $comment = $this->commentsService->update($comment, $dto);

        return $this->success(CommentResource::from($comment));
    }

    #[Route('/comments/{id}', name: 'destroy', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function destroy(int $id): JsonResponse
    {
        $comment = $this->loadComment($id);
        $this->denyAccessUnlessGranted(CommentVoter::DELETE, $comment);

        $this->commentsService->delete($comment);

        return $this->noContent();
    }

    private function loadTask(int $id): Task
    {
        $task = $this->taskRepository->find($id);
        if ($task === null) {
            throw $this->createNotFoundException('Task not found.');
        }

        return $task;
    }

    private function loadComment(int $id): Comment
    {
        $comment = $this->commentRepository->find($id);
        if ($comment === null) {
            throw $this->createNotFoundException('Comment not found.');
        }

        return $comment;
    }
}
