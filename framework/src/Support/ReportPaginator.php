<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use ArrayIterator;
use Countable;
use Fnlla\Core\Container;
use Fnlla\View\View;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, mixed>
 */
final class ReportPaginator implements IteratorAggregate, Countable
{
    private array $items;
    private int $total;
    private int $perPage;
    private int $currentPage;
    private string $baseUrl;
    private array $queryParams;

    public function __construct(
        array $items,
        int $total,
        int $perPage,
        int $currentPage,
        string $baseUrl,
        array $queryParams = []
    ) {
        $this->items = $items;
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->baseUrl = $baseUrl;
        $this->queryParams = $queryParams;
    }

    /**
     * @return Traversable<int, mixed>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        if ($this->perPage <= 0) {
            return 1;
        }
        return (int) max(1, ceil($this->total / $this->perPage));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function previousPageUrl(): ?string
    {
        if ($this->onFirstPage()) {
            return null;
        }
        return $this->url($this->currentPage - 1);
    }

    public function nextPageUrl(): ?string
    {
        if (!$this->hasMorePages()) {
            return null;
        }
        return $this->url($this->currentPage + 1);
    }

    public function url(int $page): string
    {
        $page = max(1, $page);
        $query = $this->queryParams;
        $query['page'] = $page;

        $queryString = http_build_query($query);
        if ($queryString === '') {
            return $this->baseUrl;
        }
        return $this->baseUrl . '?' . $queryString;
    }

    public function links(string $view, ?Container $app = null): string
    {
        $app = $app ?? $this->resolveApp();
        if (!$app instanceof Container) {
            return '';
        }

        return View::render($app, $view, [
            'paginator' => $this,
            'elements' => $this->elements(),
        ]);
    }

    private function elements(): array
    {
        $last = $this->lastPage();
        if ($last <= 1) {
            return [];
        }

        if ($last <= 7) {
            return [$this->rangeUrls(1, $last)];
        }

        $elements = [];

        $elements[] = $this->rangeUrls(1, 2);

        $windowStart = max(3, $this->currentPage - 1);
        $windowEnd = min($last - 2, $this->currentPage + 1);

        if ($windowStart > 3) {
            $elements[] = '...';
        }

        if ($windowStart <= $windowEnd) {
            $elements[] = $this->rangeUrls($windowStart, $windowEnd);
        }

        if ($windowEnd < $last - 2) {
            $elements[] = '...';
        }

        $elements[] = $this->rangeUrls($last - 1, $last);

        return $elements;
    }

    private function rangeUrls(int $start, int $end): array
    {
        $start = max(1, $start);
        $end = max($start, $end);

        $range = [];
        for ($page = $start; $page <= $end; $page++) {
            $range[$page] = $this->url($page);
        }

        return $range;
    }

    private function resolveApp(): ?Container
    {
        if (!function_exists('\\app')) {
            return null;
        }

        try {
            $app = \app();
        } catch (\Throwable $e) {
            return null;
        }

        return $app;
    }
}
