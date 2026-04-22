<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Rag;

use Fnlla\Ai\AiClientInterface;
use RuntimeException;

final class RagService
{
    private bool $enabled;
    private int $chunkSize;
    private int $chunkOverlap;
    private int $maxCandidates;
    private int $minContentLength;
    private int $maxContentLength;
    private RagChunker $chunker;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private AiClientInterface $ai,
        private RagRepository $repo,
        array $config = []
    ) {
        $this->enabled = (bool) ($config['enabled'] ?? false);
        $this->chunkSize = (int) ($config['chunk_size'] ?? 1200);
        $this->chunkOverlap = (int) ($config['chunk_overlap'] ?? 120);
        $this->maxCandidates = (int) ($config['max_candidates'] ?? 200);
        $this->minContentLength = (int) ($config['min_content_length'] ?? 40);
        $this->maxContentLength = (int) ($config['max_content_length'] ?? 20000);
        $this->chunker = new RagChunker($this->chunkSize, $this->chunkOverlap);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function ensureSchema(): void
    {
        $this->repo->ensureSchema();
    }

    public function count(string $namespace): int
    {
        return $this->repo->countNamespace($namespace);
    }

    public function clearNamespace(string $namespace): int
    {
        return $this->repo->deleteNamespace($namespace);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array{ok: bool, indexed: int, skipped: int, error: string}
     */
    public function indexText(
        string $namespace,
        string $content,
        array $metadata = [],
        string $sourceType = '',
        string $sourceId = ''
    ): array {
        if (!$this->enabled) {
            return ['ok' => false, 'indexed' => 0, 'skipped' => 0, 'error' => 'RAG is disabled.'];
        }

        $namespace = trim($namespace);
        if ($namespace === '') {
            return ['ok' => false, 'indexed' => 0, 'skipped' => 0, 'error' => 'Namespace is required.'];
        }

        $content = trim($content);
        if ($content === '') {
            return ['ok' => false, 'indexed' => 0, 'skipped' => 0, 'error' => 'Content is empty.'];
        }

        if (strlen($content) < $this->minContentLength) {
            return ['ok' => true, 'indexed' => 0, 'skipped' => 1, 'error' => ''];
        }

        if (strlen($content) > $this->maxContentLength) {
            $content = substr($content, 0, $this->maxContentLength);
        }

        $this->repo->ensureSchema();

        $chunks = $this->chunker->chunk($content);
        if ($chunks === []) {
            return ['ok' => true, 'indexed' => 0, 'skipped' => 1, 'error' => ''];
        }

        $indexed = 0;
        $skipped = 0;
        $chunkTotal = count($chunks);
        foreach ($chunks as $index => $chunk) {
            $hash = sha1($namespace . '|' . $sourceType . '|' . $sourceId . '|' . $index . '|' . $chunk);
            $existing = $this->repo->findByHash($namespace, $hash, $index);
            if ($existing !== null) {
                $skipped++;
                continue;
            }

            $embedding = $this->createEmbedding($chunk);
            if ($embedding === null) {
                return ['ok' => false, 'indexed' => $indexed, 'skipped' => $skipped, 'error' => 'Unable to generate embeddings.'];
            }

            $payload = [
                'namespace' => $namespace,
                'source_type' => $sourceType !== '' ? $sourceType : null,
                'source_id' => $sourceId !== '' ? $sourceId : null,
                'chunk_index' => $index,
                'chunk_total' => $chunkTotal,
                'content' => $chunk,
                'content_hash' => $hash,
                'embedding' => $this->encodeJson($embedding),
                'metadata' => $metadata !== [] ? $this->encodeJson($metadata) : null,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ];

            $this->repo->insert($payload);
            $indexed++;
        }

        return ['ok' => true, 'indexed' => $indexed, 'skipped' => $skipped, 'error' => ''];
    }

    /**
     * @return array{ok: bool, indexed: int, skipped: int, error: string}
     */
    public function indexDirectory(string $namespace, string $path, array $extensions = ['md']): array
    {
        if (!$this->enabled) {
            return ['ok' => false, 'indexed' => 0, 'skipped' => 0, 'error' => 'RAG is disabled.'];
        }

        $path = rtrim($path, '/\\');
        if ($path === '' || !is_dir($path)) {
            return ['ok' => false, 'indexed' => 0, 'skipped' => 0, 'error' => 'Directory not found.'];
        }

        $extensions = array_values(array_filter(array_map('strtolower', array_map('trim', $extensions))));
        if ($extensions === []) {
            $extensions = ['md'];
        }

        $files = $this->collectFiles($path, $extensions);
        $indexed = 0;
        $skipped = 0;
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if (!is_string($content)) {
                $skipped++;
                continue;
            }

            $title = $this->extractTitle($content, $file);
            $meta = ['path' => $file, 'title' => $title, 'kind' => 'file'];
            $result = $this->indexText($namespace, $content, $meta, 'file', $file);
            if (!$result['ok']) {
                return $result;
            }
            $indexed += $result['indexed'];
            $skipped += $result['skipped'];
        }

        return ['ok' => true, 'indexed' => $indexed, 'skipped' => $skipped, 'error' => ''];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $namespace, string $query, int $limit = 6): array
    {
        if (!$this->enabled) {
            return [];
        }

        $namespace = trim($namespace);
        $query = trim($query);
        if ($namespace === '' || $query === '') {
            return [];
        }

        $embedding = $this->createEmbedding($query);
        if ($embedding === null) {
            return [];
        }

        $rows = $this->repo->listByNamespace($namespace, $this->maxCandidates);
        $scored = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $vector = $this->decodeEmbedding($row['embedding'] ?? null);
            if ($vector === null) {
                continue;
            }
            $score = RagMath::cosine($embedding, $vector);
            if ($score <= 0.0) {
                continue;
            }
            $scored[] = [
                'score' => $score,
                'content' => (string) ($row['content'] ?? ''),
                'source_type' => (string) ($row['source_type'] ?? ''),
                'source_id' => (string) ($row['source_id'] ?? ''),
                'metadata' => $this->decodeMetadata($row['metadata'] ?? null),
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $limit = max(1, $limit);
        return array_slice($scored, 0, $limit);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     */
    public function formatContext(array $results, int $maxChars = 6000): string
    {
        if ($results === []) {
            return '';
        }

        $blocks = [];
        $total = 0;
        foreach ($results as $index => $result) {
            $content = trim((string) ($result['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $meta = $result['metadata'] ?? [];
            $label = 'Source ' . ($index + 1);
            if (is_array($meta) && isset($meta['title']) && is_string($meta['title']) && $meta['title'] !== '') {
                $label .= ': ' . $meta['title'];
            }
            $block = $label . "\n" . $content;
            $len = strlen($block);
            if ($total + $len > $maxChars) {
                break;
            }
            $blocks[] = $block;
            $total += $len + 2;
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @return float[]|null
     */
    private function createEmbedding(string $text): ?array
    {
        $response = $this->ai->embeddings([
            'input' => $text,
        ]);

        if (!$response['ok']) {
            return null;
        }

        $data = $response['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }

        $items = $data['data'] ?? null;
        if (!is_array($items) || $items === []) {
            return null;
        }

        $first = $items[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        $embedding = $first['embedding'] ?? null;
        if (!is_array($embedding)) {
            return null;
        }

        return array_map('floatval', $embedding);
    }

    /**
     * @return float[]|null
     */
    private function decodeEmbedding(mixed $value): ?array
    {
        if (is_array($value)) {
            return array_map('floatval', $value);
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return null;
        }

        return array_map('floatval', $decoded);
    }

    private function decodeMetadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode JSON value.');
        }

        return $encoded;
    }

    private function extractTitle(string $content, string $file): string
    {
        $lines = preg_split('/\r?\n/', $content) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !str_starts_with($line, '#')) {
                continue;
            }
            $title = ltrim($line, '# ');
            if ($title !== '') {
                return $title;
            }
        }

        return basename($file);
    }

    /**
     * @return string[]
     */
    private function collectFiles(string $path, array $extensions): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $ext = strtolower($fileInfo->getExtension());
            if (!in_array($ext, $extensions, true)) {
                continue;
            }
            $files[] = $fileInfo->getPathname();
        }

        return $files;
    }
}


