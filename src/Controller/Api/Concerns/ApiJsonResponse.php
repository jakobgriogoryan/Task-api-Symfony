<?php

declare(strict_types=1);

namespace App\Controller\Api\Concerns;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

trait ApiJsonResponse
{
    /**
     * @param array<string, mixed>|list<mixed>|null $data
     */
    protected function success(?array $data = null, int $status = Response::HTTP_OK): JsonResponse
    {
        $payload = ['success' => true];
        if ($data !== null) {
            $payload['data'] = $data;
        }

        return new JsonResponse($payload, $status);
    }

    /**
     * @param array<string, mixed>|list<mixed> $data
     */
    protected function created(array $data): JsonResponse
    {
        return $this->success($data, Response::HTTP_CREATED);
    }

    protected function noContent(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    protected function validationError(ConstraintViolationListInterface $violations): JsonResponse
    {
        $details = [];
        foreach ($violations as $violation) {
            $details[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return new JsonResponse(
            [
                'success' => false,
                'error' => 'Validation failed.',
                'details' => $details,
            ],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * @param array<string, mixed>|list<mixed>|null $details
     */
    protected function error(string $message, int $status, ?array $details = null): JsonResponse
    {
        $payload = ['success' => false, 'error' => $message];
        if ($details !== null) {
            $payload['details'] = $details;
        }

        return new JsonResponse($payload, $status);
    }
}
