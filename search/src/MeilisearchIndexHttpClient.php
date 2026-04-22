<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Search;

final class MeilisearchIndexHttpClient
{
    public function __construct(
        private MeilisearchHttpClient $client,
        private string $index
    ) {
    }

    public function search(string $query, array $options = []): array
    {
        return $this->client->search($this->index, $query, $options);
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     */
    public function addDocuments(array $documents, ?string $primaryKey = null): array
    {
        $payload = $documents;
        $path = '/indexes/' . rawurlencode($this->index) . '/documents';
        if ($primaryKey !== null && $primaryKey !== '') {
            $path .= '?primaryKey=' . rawurlencode($primaryKey);
        }
        return $this->client->requestJson('POST', $path, $payload);
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     */
    public function updateDocuments(array $documents): array
    {
        $path = '/indexes/' . rawurlencode($this->index) . '/documents';
        return $this->client->requestJson('PUT', $path, $documents);
    }

    public function deleteDocument(string|int $documentId): array
    {
        $path = '/indexes/' . rawurlencode($this->index) . '/documents/' . rawurlencode((string) $documentId);
        return $this->client->requestJson('DELETE', $path);
    }

    /**
     * @param array<int, string|int> $documentIds
     */
    public function deleteDocuments(array $documentIds): array
    {
        $path = '/indexes/' . rawurlencode($this->index) . '/documents/delete-batch';
        return $this->client->requestJson('POST', $path, $documentIds);
    }

    public function stats(): array
    {
        return $this->client->requestJson('GET', '/indexes/' . rawurlencode($this->index) . '/stats');
    }
}
