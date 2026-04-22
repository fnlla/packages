<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Pdf\Templates;

interface PdfTemplateInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function render(array $data = []): string;
}


