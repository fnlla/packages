<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Debugbar\Middleware;

use Fnlla\Debugbar\DebugbarCollector;
use Fnlla\Http\Response;
use Fnlla\Http\Stream;
use Fnlla\Support\Env;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class DebugbarMiddleware implements MiddlewareInterface
{
    private const JS_ASSET_PATH = '/_fnlla/debugbar.js';
    private const ASSET_VERSION = '3.0.0';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isAssetRequest($request)) {
            return $this->serveAsset($request);
        }

        DebugbarCollector::reset();
        DebugbarCollector::init();
        DebugbarCollector::mark('request.start', 0.0);

        $response = $handler->handle($request);
        if (!$response instanceof Response) {
            return $response;
        }

        $queries = DebugbarCollector::queries();
        $messages = DebugbarCollector::messages();
        $errors = DebugbarCollector::errors();
        $timeline = DebugbarCollector::timeline();

        $totalQueryMs = $this->sumQueryTime($queries);
        $requestMs = DebugbarCollector::requestTimeMs();
        DebugbarCollector::mark('request.end', $requestMs);
        $timeline = DebugbarCollector::timeline();

        $slowQueryMs = $this->toFloat(Env::get('DEBUGBAR_SLOW_QUERY_MS', 25.0), 25.0);
        $slowQueryCount = $this->countSlowQueries($queries, $slowQueryMs);
        $memoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $response = $response
            ->withHeader('X-Debug-Queries', (string) count(DebugbarCollector::queries()))
            ->withHeader('X-Debug-Messages', (string) count(DebugbarCollector::messages()))
            ->withHeader('X-Debug-Errors', (string) count(DebugbarCollector::errors()))
            ->withHeader('X-Debug-Time-Ms', (string) $requestMs)
            ->withHeader('X-Debug-Slow-Queries', (string) $slowQueryCount)
            ->withHeader('X-Debug-Memory-Mb', (string) $memoryMb);

        if (!$this->isUiEnabled()) {
            return $response;
        }

        if (!$this->isHtmlResponse($response)) {
            return $response;
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            return $response;
        }

        $maxRows = $this->toInt(Env::get('DEBUGBAR_MAX_ROWS', 120), 120);
        $panel = $this->renderPanel(
            $request,
            $response,
            $queries,
            $messages,
            $errors,
            $timeline,
            $requestMs,
            $totalQueryMs,
            $slowQueryCount,
            $slowQueryMs,
            $memoryMb,
            $maxRows
        );

        $updatedBody = str_contains(strtolower($body), '</body>')
            ? preg_replace('/<\/body>/i', $panel . '</body>', $body, 1) ?? ($body . $panel)
            : ($body . $panel);

        return $response->withBody(Stream::fromString($updatedBody));
    }

    private function isUiEnabled(): bool
    {
        return $this->toBool(Env::get('DEBUGBAR_UI_ENABLED', true), true);
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType !== '' && str_contains($contentType, 'text/html')) {
            return true;
        }

        $body = (string) $response->getBody();
        return str_contains(strtolower($body), '<html');
    }

    private function sumQueryTime(array $queries): float
    {
        $total = 0.0;
        foreach ($queries as $query) {
            if (!is_array($query)) {
                continue;
            }
            $total += (float) ($query['time_ms'] ?? 0.0);
        }
        return round($total, 2);
    }

    private function countSlowQueries(array $queries, float $thresholdMs): int
    {
        $count = 0;
        foreach ($queries as $query) {
            if (!is_array($query)) {
                continue;
            }
            if ((float) ($query['time_ms'] ?? 0.0) >= $thresholdMs) {
                $count++;
            }
        }
        return $count;
    }

    private function renderPanel(
        ServerRequestInterface $request,
        Response $response,
        array $queries,
        array $messages,
        array $errors,
        array $timeline,
        float $requestMs,
        float $totalQueryMs,
        int $slowQueryCount,
        float $slowQueryMs,
        float $memoryMb,
        int $maxRows
    ): string {
        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();
        if ($query !== '') {
            $path .= '?' . $query;
        }

        $status = $response->getStatusCode();
        $method = strtoupper($request->getMethod());
        $responseBytes = strlen((string) $response->getBody());

        $queriesHtml = $this->renderQueries($queries, $slowQueryMs, $maxRows);
        $messagesHtml = $this->renderMessages($messages, $maxRows);
        $errorsHtml = $this->renderErrors($errors, $maxRows);
        $timelineHtml = $this->renderTimeline($timeline, $requestMs, $maxRows);
        $headersHtml = $this->renderHeaders($request, $response);
        $errorCount = count($errors);
        $toggleClass = $errorCount > 0 ? ' fdbg-toggle-danger' : '';
        $statusLabel = $errorCount > 0 ? 'issues detected' : 'healthy';

        $summaryCards = [
            $this->summaryCard('Request', sprintf('%.2f ms', $requestMs), $method . ' ' . $path),
            $this->summaryCard('Response', (string) $status, $this->formatBytes($responseBytes)),
            $this->summaryCard('Queries', (string) count($queries), sprintf('%.2f ms total', $totalQueryMs)),
            $this->summaryCard('Slow Queries', (string) $slowQueryCount, '>=' . sprintf('%.1f ms', $slowQueryMs)),
            $this->summaryCard('Messages', (string) count($messages), 'app logs'),
            $this->summaryCard('Errors', (string) count($errors), 'captured'),
            $this->summaryCard('Peak Memory', sprintf('%.2f MB', $memoryMb), 'process peak'),
            $this->summaryCard('Timeline Marks', (string) count($timeline), 'collector'),
        ];

        $summaryHtml = implode('', $summaryCards);
        $badge = sprintf(
            'REQ %.1fms | SQL %d | ERR %d | %s',
            $requestMs,
            count($queries),
            $errorCount,
            strtoupper($statusLabel)
        );
        $debugbarJs = $this->e($this->assetUrl(self::JS_ASSET_PATH));

        return <<<HTML
<style>
:root {
  --fdbg-bg: var(--f-color-bg, #f1f5f9);
  --fdbg-panel: #ffffff;
  --fdbg-text: var(--f-color-text, #0f172a);
  --fdbg-muted: var(--f-color-muted, #64748b);
  --fdbg-border: var(--f-color-border, #cbd5e1);
  --fdbg-accent: #0f172a;
  --fdbg-accent-contrast: #ffffff;
  --fdbg-danger: #dc2626;
  --fdbg-warning: #d97706;
  --fdbg-success: #0f766e;
  --fdbg-info: #1d4ed8;
}
.fdbg-root, .fdbg-root * { box-sizing: border-box; font-family: var(--f-font-sans, ui-sans-serif, -apple-system, "Segoe UI", sans-serif); }
.fdbg-root { position: fixed; right: 16px; bottom: 16px; z-index: 2147483640; color: var(--fdbg-text); }
.fdbg-toggle {
  border: 1px solid var(--fdbg-border);
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  color: var(--fdbg-accent-contrast);
  border-radius: 999px;
  padding: 9px 14px;
  cursor: pointer;
  font-size: 12px;
  letter-spacing: 0.01em;
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.24);
}
.fdbg-toggle-danger {
  background: linear-gradient(135deg, #991b1b 0%, #dc2626 100%);
}
.fdbg-panel {
  margin-top: 10px;
  width: min(1080px, calc(100vw - 32px));
  max-height: min(76vh, 820px);
  background: var(--fdbg-panel);
  border: 1px solid var(--fdbg-border);
  border-radius: 14px;
  box-shadow: 0 28px 70px rgba(15, 23, 42, 0.28);
  display: none;
  overflow: hidden;
}
.fdbg-root[data-open=\"1\"] .fdbg-panel { display: flex; flex-direction: column; }
.fdbg-head {
  padding: 12px 14px;
  border-bottom: 1px solid var(--fdbg-border);
  display: flex;
  gap: 10px;
  align-items: center;
  justify-content: space-between;
  background: #fff;
}
.fdbg-title { font-size: 14px; font-weight: 700; margin: 0; }
.fdbg-sub { color: var(--fdbg-muted); font-size: 12px; }
.fdbg-chip-row { margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap; }
.fdbg-chip { border: 1px solid var(--fdbg-border); border-radius: 999px; padding: 2px 8px; font-size: 11px; background: #f8fafc; color: #0f172a; }
.fdbg-close { border: 1px solid var(--fdbg-border); background: #fff; border-radius: 8px; padding: 6px 8px; cursor: pointer; }
.fdbg-tabs {
  display: flex;
  gap: 6px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--fdbg-border);
  overflow-x: auto;
  background: #fcfdff;
}
.fdbg-tab-btn { border: 1px solid var(--fdbg-border); background: #fff; border-radius: 999px; padding: 6px 10px; font-size: 12px; cursor: pointer; white-space: nowrap; }
.fdbg-tab-btn[aria-selected=\"true\"] { background: var(--fdbg-accent); color: var(--fdbg-accent-contrast); border-color: var(--fdbg-accent); }
.fdbg-body { padding: 12px; overflow: auto; background: var(--fdbg-bg); }
.fdbg-pane { display: none; }
.fdbg-pane.is-active { display: block; }
.fdbg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 8px; margin-bottom: 12px; }
.fdbg-card { background: #fff; border: 1px solid var(--fdbg-border); border-radius: 10px; padding: 10px; }
.fdbg-card-k { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--fdbg-muted); }
.fdbg-card-v { margin: 0; font-weight: 700; font-size: 14px; }
.fdbg-card-s { margin-top: 4px; font-size: 12px; color: var(--fdbg-muted); }
.fdbg-toolbar { margin: 0 0 10px; display: flex; gap: 8px; align-items: center; }
.fdbg-input { width: 100%; border: 1px solid var(--fdbg-border); border-radius: 8px; padding: 7px 9px; font-size: 12px; }
.fdbg-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid var(--fdbg-border); border-radius: 10px; overflow: hidden; }
.fdbg-table th, .fdbg-table td { border-bottom: 1px solid var(--fdbg-border); padding: 8px; text-align: left; font-size: 12px; vertical-align: top; }
.fdbg-table th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--fdbg-muted); background: #f8fafc; }
.fdbg-table tr:last-child td { border-bottom: 0; }
.fdbg-sql { font-family: var(--f-font-mono, ui-monospace, SFMono-Regular, Menlo, monospace); white-space: pre-wrap; }
.fdbg-badge { display: inline-block; border-radius: 999px; padding: 2px 7px; font-size: 10px; border: 1px solid var(--fdbg-border); background: #fff; }
.fdbg-badge-danger { border-color: #fecaca; color: #991b1b; background: #fef2f2; }
.fdbg-list { display: grid; gap: 8px; }
.fdbg-item { border: 1px solid var(--fdbg-border); border-radius: 10px; background: #fff; padding: 9px; }
.fdbg-item-head { font-size: 11px; color: var(--fdbg-muted); margin-bottom: 5px; display: flex; gap: 8px; }
.fdbg-progress { height: 6px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.fdbg-progress > span { display: block; height: 100%; background: var(--fdbg-success); }
.fdbg-empty { border: 1px dashed var(--fdbg-border); border-radius: 10px; padding: 14px; background: #fff; color: var(--fdbg-muted); font-size: 12px; }
.fdbg-copy { border: 1px solid var(--fdbg-border); background: #fff; border-radius: 7px; font-size: 11px; padding: 4px 7px; cursor: pointer; }
.fdbg-copy:active { transform: translateY(1px); }
.fdbg-kbd { font-family: var(--f-font-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 11px; border: 1px solid var(--fdbg-border); border-bottom-width: 2px; border-radius: 6px; padding: 1px 5px; background: #fff; }
</style>
<div class="fdbg-root" id="fdbg-root" data-open="0">
  <button type="button" class="fdbg-toggle{$toggleClass}" data-fdbg-toggle title="Toggle debugbar (Ctrl+Shift+D)">{$this->e($badge)}</button>
  <section class="fdbg-panel" role="dialog" aria-label="Fnlla Debugbar">
    <header class="fdbg-head">
      <div>
        <p class="fdbg-title">Fnlla Debugbar</p>
        <div class="fdbg-sub">{$this->e($method)} {$this->e($path)} | HTTP {$status}</div>
        <div class="fdbg-chip-row">
          <span class="fdbg-chip">Request: {$this->e(sprintf('%.2f ms', $requestMs))}</span>
          <span class="fdbg-chip">SQL: {$this->e((string) count($queries))}</span>
          <span class="fdbg-chip">Errors: {$this->e((string) $errorCount)}</span>
          <span class="fdbg-chip">State: {$this->e($statusLabel)}</span>
        </div>
      </div>
      <button type="button" class="fdbg-close" data-fdbg-close>Close <span class="fdbg-kbd">Esc</span></button>
    </header>
    <nav class="fdbg-tabs">
      <button type="button" class="fdbg-tab-btn" data-fdbg-tab="summary" aria-selected="true">Summary</button>
      <button type="button" class="fdbg-tab-btn" data-fdbg-tab="queries" aria-selected="false">Queries</button>
      <button type="button" class="fdbg-tab-btn" data-fdbg-tab="timeline" aria-selected="false">Timeline</button>
      <button type="button" class="fdbg-tab-btn" data-fdbg-tab="headers" aria-selected="false">Headers</button>
      <button type="button" class="fdbg-tab-btn" data-fdbg-tab="messages" aria-selected="false">Messages</button>
      <button type="button" class="fdbg-tab-btn" data-fdbg-tab="errors" aria-selected="false">Errors</button>
    </nav>
    <div class="fdbg-body">
      <section class="fdbg-pane is-active" data-fdbg-pane="summary">
        <div class="fdbg-grid">{$summaryHtml}</div>
      </section>
      <section class="fdbg-pane" data-fdbg-pane="queries">
        {$queriesHtml}
      </section>
      <section class="fdbg-pane" data-fdbg-pane="timeline">
        {$timelineHtml}
      </section>
      <section class="fdbg-pane" data-fdbg-pane="headers">
        {$headersHtml}
      </section>
      <section class="fdbg-pane" data-fdbg-pane="messages">
        {$messagesHtml}
      </section>
      <section class="fdbg-pane" data-fdbg-pane="errors">
        {$errorsHtml}
      </section>
    </div>
  </section>
</div>
<script src="{$debugbarJs}" defer></script>
HTML;
    }

    private function isAssetRequest(ServerRequestInterface $request): bool
    {
        $path = strtolower($request->getUri()->getPath());
        return str_ends_with($path, self::JS_ASSET_PATH);
    }

    private function serveAsset(ServerRequestInterface $request): ResponseInterface
    {
        $path = strtolower($request->getUri()->getPath());
        if (!str_ends_with($path, self::JS_ASSET_PATH)) {
            return Response::text('Not Found', 404);
        }

        $asset = dirname(__DIR__, 2) . '/resources/debugbar.js';
        $payload = @file_get_contents($asset);
        if (!is_string($payload) || $payload === '') {
            return Response::text('Debugbar asset unavailable', 404);
        }

        return new Response(200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ], Stream::fromString($payload));
    }

    private function assetUrl(string $path): string
    {
        return $path . '?v=' . self::ASSET_VERSION;
    }

    private function renderQueries(array $queries, float $slowQueryMs, int $maxRows): string
    {
        if ($queries === []) {
            return '<div class="fdbg-empty">No SQL queries captured for this request.</div>';
        }

        $rows = [];
        $rendered = 0;
        foreach ($queries as $query) {
            if ($rendered >= $maxRows) {
                break;
            }
            if (!is_array($query)) {
                continue;
            }

            $sql = (string) ($query['sql'] ?? '');
            $params = (array) ($query['params'] ?? []);
            $timeMs = (float) ($query['time_ms'] ?? 0.0);
            $rowCount = (int) ($query['row_count'] ?? 0);
            $source = (string) ($query['source'] ?? 'pdo');
            $isSlow = $timeMs >= $slowQueryMs;
            $slowBadge = $isSlow ? '<span class="fdbg-badge fdbg-badge-danger">slow</span>' : '';

            $paramsJson = $params === [] ? '-' : $this->e((string) json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $rows[] = sprintf(
                '<tr data-fdbg-query-row data-fdbg-query-text="%s"><td><div class="fdbg-sql">%s</div></td><td>%s %s</td><td>%d</td><td>%s</td><td><span class="fdbg-badge">%s</span></td><td><button type="button" class="fdbg-copy" data-fdbg-copy="%s">Copy SQL</button></td></tr>',
                $this->e($sql . ' ' . $paramsJson . ' ' . $source),
                $this->e($sql === '' ? '(empty query)' : $sql),
                $this->e(sprintf('%.2f ms', $timeMs)),
                $slowBadge,
                $rowCount,
                $paramsJson,
                $this->e($source),
                $this->e($sql)
            );
            $rendered++;
        }

        $truncated = count($queries) > $rendered
            ? '<div class="fdbg-sub">Showing first ' . $rendered . ' of ' . count($queries) . ' queries.</div>'
            : '';

        return '<div class="fdbg-toolbar"><input class="fdbg-input" type="search" placeholder="Filter SQL / params..." data-fdbg-query-filter></div>'
            . $truncated
            . '<table class="fdbg-table"><thead><tr><th>SQL</th><th>Time</th><th>Rows</th><th>Params</th><th>Source</th><th>Actions</th></tr></thead><tbody>'
            . implode('', $rows)
            . '</tbody></table>';
    }

    private function renderMessages(array $messages, int $maxRows): string
    {
        if ($messages === []) {
            return '<div class="fdbg-empty">No debug messages captured.</div>';
        }

        $items = [];
        $rendered = 0;
        foreach ($messages as $message) {
            if ($rendered >= $maxRows) {
                break;
            }
            if (!is_array($message)) {
                continue;
            }
            $level = strtolower((string) ($message['level'] ?? 'info'));
            $context = (array) ($message['context'] ?? []);
            $contextJson = $context === [] ? '' : '<pre class="fdbg-sql">' . $this->e((string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . '</pre>';
            $items[] = '<div class="fdbg-item"><div class="fdbg-item-head"><span class="fdbg-badge">' . $this->e($level) . '</span><span>' . $this->e((string) ($message['time'] ?? '')) . '</span></div><div>'
                . $this->e((string) ($message['message'] ?? ''))
                . '</div>'
                . $contextJson
                . '</div>';
            $rendered++;
        }

        return '<div class="fdbg-list">' . implode('', $items) . '</div>';
    }

    private function renderErrors(array $errors, int $maxRows): string
    {
        if ($errors === []) {
            return '<div class="fdbg-empty">No errors captured.</div>';
        }

        $items = [];
        $rendered = 0;
        foreach ($errors as $error) {
            if ($rendered >= $maxRows) {
                break;
            }
            if (!is_array($error)) {
                continue;
            }
            $trace = (string) ($error['trace'] ?? '');
            $traceHtml = $trace !== '' ? '<pre class="fdbg-sql">' . $this->e($trace) . '</pre>' : '';
            $items[] = '<div class="fdbg-item"><div class="fdbg-item-head"><span class="fdbg-badge fdbg-badge-danger">'
                . $this->e((string) ($error['type'] ?? 'error'))
                . '</span><span>'
                . $this->e((string) ($error['time'] ?? ''))
                . '</span></div><div>'
                . $this->e((string) ($error['message'] ?? ''))
                . '</div><div class="fdbg-sub">'
                . $this->e((string) ($error['file'] ?? ''))
                . ':' . $this->e((string) ($error['line'] ?? 0))
                . '</div>'
                . $traceHtml
                . '</div>';
            $rendered++;
        }

        return '<div class="fdbg-list">' . implode('', $items) . '</div>';
    }

    private function renderTimeline(array $timeline, float $requestMs, int $maxRows): string
    {
        if ($timeline === []) {
            return '<div class="fdbg-empty">No timeline marks captured.</div>';
        }

        $items = [];
        $rendered = 0;
        $denominator = max($requestMs, 0.1);

        foreach ($timeline as $entry) {
            if ($rendered >= $maxRows) {
                break;
            }
            if (!is_array($entry)) {
                continue;
            }

            $label = (string) ($entry['label'] ?? 'mark');
            $ms = (float) ($entry['ms'] ?? 0.0);
            $ratio = min(100.0, max(0.0, ($ms / $denominator) * 100.0));
            $items[] = '<div class="fdbg-item"><div class="fdbg-item-head"><span>'
                . $this->e($label)
                . '</span><span>'
                . $this->e(sprintf('%.2f ms', $ms))
                . '</span></div><div class="fdbg-progress"><span style="width: '
                . $this->e(sprintf('%.2f', $ratio))
                . '%;"></span></div></div>';
            $rendered++;
        }

        return '<div class="fdbg-list">' . implode('', $items) . '</div>';
    }

    private function renderHeaders(ServerRequestInterface $request, Response $response): string
    {
        $requestRows = [];
        foreach ($request->getHeaders() as $name => $values) {
            if (!is_array($values)) {
                continue;
            }
            $requestRows[] = '<tr><td>' . $this->e((string) $name) . '</td><td>' . $this->e(implode(', ', $values)) . '</td></tr>';
        }
        if ($requestRows === []) {
            $requestRows[] = '<tr><td colspan="2" class="fdbg-sub">No request headers.</td></tr>';
        }

        $responseRows = [];
        foreach ($response->getHeaders() as $name => $values) {
            if (!is_array($values)) {
                continue;
            }
            $responseRows[] = '<tr><td>' . $this->e((string) $name) . '</td><td>' . $this->e(implode(', ', $values)) . '</td></tr>';
        }
        if ($responseRows === []) {
            $responseRows[] = '<tr><td colspan="2" class="fdbg-sub">No response headers.</td></tr>';
        }

        return '<div class="fdbg-grid">'
            . '<div class="fdbg-card"><p class="fdbg-card-k">Request Headers</p><table class="fdbg-table"><thead><tr><th>Name</th><th>Value</th></tr></thead><tbody>'
            . implode('', $requestRows)
            . '</tbody></table></div>'
            . '<div class="fdbg-card"><p class="fdbg-card-k">Response Headers</p><table class="fdbg-table"><thead><tr><th>Name</th><th>Value</th></tr></thead><tbody>'
            . implode('', $responseRows)
            . '</tbody></table></div>'
            . '</div>';
    }

    private function summaryCard(string $label, string $value, string $sub): string
    {
        return '<div class="fdbg-card"><p class="fdbg-card-k">' . $this->e($label) . '</p><p class="fdbg-card-v">'
            . $this->e($value)
            . '</p><div class="fdbg-card-s">'
            . $this->e($sub)
            . '</div></div>';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return sprintf('%.2f KB', $bytes / 1024);
        }
        return sprintf('%.2f MB', $bytes / 1024 / 1024);
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }
        return $default;
    }

    private function toInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return $default;
    }

    private function toFloat(mixed $value, float $default): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        return $default;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
