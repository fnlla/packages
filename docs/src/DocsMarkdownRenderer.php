<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Docs;

final class DocsMarkdownRenderer
{
    private DocsMarkdownParser $parser;

    public function __construct(?DocsMarkdownParser $parser = null)
    {
        $this->parser = $parser ?? new DocsMarkdownParser();
    }

    public function toHtml(string $markdown): string
    {
        return $this->parser->parse($markdown);
    }
}

