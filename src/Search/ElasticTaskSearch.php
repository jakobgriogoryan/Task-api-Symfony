<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Task;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Elasticsearch driver using Symfony HttpClient directly — keeps the
 * dependency footprint small and works against Elasticsearch 8 / OpenSearch.
 *
 * Indices are created lazily on first write. The indexer is invoked
 * synchronously here; in production it runs from a Messenger handler
 * reacting to Doctrine lifecycle events so request latency is unaffected.
 */
class ElasticTaskSearch implements TaskSearchInterface
{
    private const INDEX = 'tasks';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $endpoint,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function search(string $query, int $page = 1, int $perPage = 15): array
    {
        if (!$this->available()) {
            return ['ids' => [], 'total' => 0, 'driver' => 'elastic_unavailable'];
        }

        $from = ($page - 1) * $perPage;

        try {
            $response = $this->httpClient->request('POST', $this->url('_search'), [
                'json' => [
                    'from' => $from,
                    'size' => $perPage,
                    'query' => [
                        'multi_match' => [
                            'query' => $query,
                            'fields' => ['title^3', 'description'],
                            'fuzziness' => 'AUTO',
                        ],
                    ],
                ],
                'timeout' => 2.5,
            ]);

            /** @var array{hits?: array{total?: array{value?: int}, hits?: array<int, array{_id?: string}>}} $body */
            $body = $response->toArray(false);
            $hits = $body['hits']['hits'] ?? [];
            $total = (int) ($body['hits']['total']['value'] ?? 0);

            $ids = [];
            foreach ($hits as $hit) {
                if (isset($hit['_id'])) {
                    $ids[] = (int) $hit['_id'];
                }
            }

            return ['ids' => $ids, 'total' => $total, 'driver' => 'elastic'];
        } catch (\Throwable $e) {
            $this->logger->warning('Elasticsearch query failed.', ['error' => $e->getMessage()]);

            return ['ids' => [], 'total' => 0, 'driver' => 'elastic_error'];
        }
    }

    public function index(Task $task): void
    {
        if (!$this->available() || $task->getId() === null) {
            return;
        }

        try {
            $this->httpClient->request('PUT', $this->url('_doc/'.$task->getId()), [
                'json' => [
                    'title' => $task->getTitle(),
                    'description' => $task->getDescription(),
                    'status' => $task->getStatus()->value,
                    'project_id' => $task->getProject()?->getId(),
                    'assignee_id' => $task->getAssignee()?->getId(),
                    'updated_at' => $task->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                ],
                'timeout' => 2.5,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Elasticsearch index failed.', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function remove(int $taskId): void
    {
        if (!$this->available()) {
            return;
        }

        try {
            $this->httpClient->request('DELETE', $this->url('_doc/'.$taskId), [
                'timeout' => 2.5,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Elasticsearch delete failed.', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->endpoint, '/').'/'.self::INDEX.'/'.ltrim($path, '/');
    }

    private function available(): bool
    {
        return $this->endpoint !== null && $this->endpoint !== '';
    }
}
