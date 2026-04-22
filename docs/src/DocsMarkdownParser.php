<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Docs;

final class DocsMarkdownParser
{
    public function parse(string $markdown): string
    {
        $lines = preg_split('/\r\n|\n|\r/', $markdown);
        if ($lines === false) {
            $lines = [];
        }
        $html = [];
        $count = count($lines);
        $i = 0;

        while ($i < $count) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '') {
                $i++;
                continue;
            }

            if (preg_match('/^```[\t ]*([a-z0-9_-]+)?[\t ]*$/i', $line, $matches) === 1) {
                $lang = strtolower($matches[1] ?? '');
                $codeLines = [];
                $i++;
                while ($i < $count && preg_match('/^```[\t ]*$/', $lines[$i]) !== 1) {
                    $codeLines[] = $lines[$i];
                    $i++;
                }
                $i++;
                $code = $this->escape(implode("\n", $codeLines));
                $class = $lang !== '' ? ' class="language-' . $this->escapeAttr($lang) . '"' : '';
                $html[] = '<pre><code' . $class . '>' . $code . '</code></pre>';
                continue;
            }

            $nextLine = $lines[$i + 1] ?? '';
            if ($this->isTableStart($line, $nextLine)) {
                $tableLines = [$line, $nextLine];
                $i += 2;
                while ($i < $count) {
                    $row = $lines[$i];
                    if (trim($row) === '' || !str_contains($row, '|')) {
                        break;
                    }
                    $tableLines[] = $row;
                    $i++;
                }
                $html[] = $this->renderTable($tableLines);
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches) === 1) {
                $level = strlen($matches[1]);
                $text = trim($matches[2]);
                $id = $this->slugify($text);
                $html[] = '<h' . $level . ' id="' . $this->escapeAttr($id) . '">' . $this->inline($text) . '</h' . $level . '>';
                $i++;
                continue;
            }

            if (preg_match('/^\s*([-*_])\1\1+\s*$/', $line) === 1) {
                $html[] = '<hr>';
                $i++;
                continue;
            }

            if (preg_match('/^\s*>\s?(.*)$/', $line) === 1) {
                $quoteLines = [];
                while ($i < $count && preg_match('/^\s*>\s?(.*)$/', $lines[$i], $m) === 1) {
                    $quoteLines[] = $m[1];
                    $i++;
                }
                $html[] = '<blockquote>' . $this->parse(implode("\n", $quoteLines)) . '</blockquote>';
                continue;
            }

            if ($this->isListStart($line)) {
                [$listHtml, $nextIndex] = $this->renderList($lines, $i);
                $html[] = $listHtml;
                $i = $nextIndex;
                continue;
            }

            $paragraphLines = [];
            while ($i < $count) {
                $candidate = $lines[$i];
                $candidateNext = $lines[$i + 1] ?? '';
                if (trim($candidate) === '' || $this->isBlockStart($candidate, $candidateNext)) {
                    break;
                }
                $paragraphLines[] = trim($candidate);
                $i++;
            }

            $paragraph = trim(implode(' ', $paragraphLines));
            if ($paragraph !== '') {
                $html[] = '<p>' . $this->inline($paragraph) . '</p>';
                continue;
            }

            $i++;
        }

        return implode('', $html);
    }

    private function isBlockStart(string $line, string $nextLine): bool
    {
        return preg_match('/^(#{1,6})\s+/', $line) === 1
            || preg_match('/^```/', $line) === 1
            || preg_match('/^\s*>/', $line) === 1
            || preg_match('/^\s*([-*_])\1\1+\s*$/', $line) === 1
            || $this->isListStart($line)
            || $this->isTableStart($line, $nextLine);
    }

    private function isListStart(string $line): bool
    {
        return preg_match('/^\s*[-+*]\s+.+$/', $line) === 1
            || preg_match('/^\s*\d+\.\s+.+$/', $line) === 1;
    }

    /**
     * @param array<int, string> $lines
     * @return array{string, int}
     */
    private function renderList(array $lines, int $startIndex): array
    {
        $ordered = preg_match('/^\s*\d+\.\s+/', $lines[$startIndex] ?? '') === 1;
        $tag = $ordered ? 'ol' : 'ul';
        $items = [];
        $i = $startIndex;
        $count = count($lines);

        while ($i < $count) {
            $line = $lines[$i];
            if ($ordered) {
                if (preg_match('/^\s*\d+\.\s+(.+)$/', $line, $matches) !== 1) {
                    break;
                }
            } else {
                if (preg_match('/^\s*[-+*]\s+(.+)$/', $line, $matches) !== 1) {
                    break;
                }
            }

            $items[] = '<li>' . $this->inline($matches[1]) . '</li>';
            $i++;
        }

        return ['<' . $tag . '>' . implode('', $items) . '</' . $tag . '>', $i];
    }

    private function isTableStart(string $line, string $nextLine): bool
    {
        if (!str_contains($line, '|') || !str_contains($nextLine, '|')) {
            return false;
        }

        return preg_match('/^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)+\|?\s*$/', $nextLine) === 1;
    }

    /**
     * @param array<int, string> $rows
     */
    private function renderTable(array $rows): string
    {
        $header = $this->splitTableRow($rows[0] ?? '');
        $bodyRows = array_slice($rows, 2);

        $headCells = [];
        foreach ($header as $cell) {
            $headCells[] = '<th>' . $this->inline($cell) . '</th>';
        }

        $bodyHtml = [];
        foreach ($bodyRows as $row) {
            $cells = $this->splitTableRow($row);
            if ($cells === []) {
                continue;
            }
            $td = [];
            foreach ($cells as $cell) {
                $td[] = '<td>' . $this->inline($cell) . '</td>';
            }
            $bodyHtml[] = '<tr>' . implode('', $td) . '</tr>';
        }

        return '<table><thead><tr>' . implode('', $headCells) . '</tr></thead><tbody>' . implode('', $bodyHtml) . '</tbody></table>';
    }

    /**
     * @return array<int, string>
     */
    private function splitTableRow(string $row): array
    {
        $trimmed = trim(trim($row), '|');
        if ($trimmed === '') {
            return [];
        }

        $parts = explode('|', $trimmed);
        $cells = [];
        foreach ($parts as $part) {
            $cells[] = trim($part);
        }
        return $cells;
    }

    private function inline(string $text): string
    {
        $placeholders = [];
        $idx = 0;

        $text = preg_replace_callback('/`([^`]+)`/', function (array $matches) use (&$placeholders, &$idx): string {
            $key = '[[CODE_' . $idx . ']]';
            $placeholders[$key] = '<code>' . $this->escape($matches[1]) . '</code>';
            $idx++;
            return $key;
        }, $text) ?? $text;

        $escaped = $this->escape($text);

        $escaped = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', function (array $matches): string {
            $label = $this->escape($matches[1]);
            $target = htmlspecialchars_decode($matches[2], ENT_QUOTES);
            $href = $this->sanitizeLinkTarget($target);
            if ($href === null) {
                return $label;
            }

            $rel = $this->isExternalLink($href) ? ' rel="noopener noreferrer"' : '';
            return '<a href="' . $this->escapeAttr($href) . '"' . $rel . '>' . $label . '</a>';
        }, $escaped) ?? $escaped;

        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped) ?? $escaped;

        foreach ($placeholders as $key => $value) {
            $escaped = str_replace($key, $value, $escaped);
        }

        return $escaped;
    }

    private function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text) ?? '';
        $text = preg_replace('/\s+/', '-', trim($text)) ?? '';
        $text = trim($text, '-');
        return $text !== '' ? $text : 'section';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function sanitizeLinkTarget(string $target): ?string
    {
        $target = trim($target);
        if ($target === '') {
            return null;
        }

        $target = preg_replace('/[\x00-\x1F\x7F]/', '', $target) ?? '';
        if ($target === '') {
            return null;
        }

        if (
            str_starts_with($target, '#')
            || str_starts_with($target, '/')
            || str_starts_with($target, './')
            || str_starts_with($target, '../')
            || str_starts_with($target, '?')
        ) {
            return $target;
        }

        if (str_starts_with($target, '//')) {
            return null;
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*)\s*:/i', $target, $matches) === 1) {
            $scheme = strtolower(trim($matches[1]));
            if (!in_array($scheme, ['http', 'https', 'mailto'], true)) {
                return null;
            }
        }

        $scheme = parse_url($target, PHP_URL_SCHEME);
        if (!is_string($scheme) || $scheme === '') {
            return $target;
        }

        return in_array(strtolower($scheme), ['http', 'https', 'mailto'], true) ? $target : null;
    }

    private function isExternalLink(string $href): bool
    {
        $href = strtolower($href);
        return str_starts_with($href, 'http://') || str_starts_with($href, 'https://');
    }
}
