<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Pdf;

interface PdfRendererInterface
{
    /**
     * Render HTML into a PDF binary string.
     *
     * @param array<string, mixed> $options
     */
    public function render(string $html, array $options = []): string;
}


