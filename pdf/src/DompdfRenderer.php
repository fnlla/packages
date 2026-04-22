<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

final class DompdfRenderer implements PdfRendererInterface
{
    /** @var array<string, mixed> */
    private array $defaults;

    /**
     * @param array<string, mixed> $defaults
     */
    public function __construct(array $defaults = [])
    {
        $this->defaults = $defaults;
    }

    public function render(string $html, array $options = []): string
    {
        $config = array_merge($this->defaults, $options);
        $paper = (string) ($config['paper'] ?? 'A4');
        $orientation = (string) ($config['orientation'] ?? 'portrait');
        $defaultFont = (string) ($config['default_font'] ?? 'DejaVu Sans');
        $remoteEnabled = (bool) ($config['remote_enabled'] ?? false);
        $dpi = (int) ($config['dpi'] ?? 96);

        $dompdfOptions = new Options();
        if ($defaultFont !== '') {
            $dompdfOptions->set('defaultFont', $defaultFont);
        }
        $dompdfOptions->set('isRemoteEnabled', $remoteEnabled);
        if ($dpi > 0) {
            $dompdfOptions->set('dpi', $dpi);
        }

        $dompdf = new Dompdf($dompdfOptions);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }
}


