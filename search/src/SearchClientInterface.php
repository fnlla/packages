<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Search;

interface SearchClientInterface
{
    public function search(string $index, string $query, array $options = []): array;

    public function index(string $index): mixed;
}
