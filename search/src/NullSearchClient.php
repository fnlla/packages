<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Search;

final class NullSearchClient implements SearchClientInterface
{
    public function search(string $index, string $query, array $options = []): array
    {
        return [
            'hits' => [],
            'query' => $query,
            'index' => $index,
            'total' => 0,
        ];
    }

    public function index(string $index): mixed
    {
        return null;
    }
}
