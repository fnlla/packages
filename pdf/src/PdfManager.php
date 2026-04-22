<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Pdf;

use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Response;
use Fnlla\Http\Stream;
use Fnlla\Pdf\Templates\PdfTemplateInterface;

final class PdfManager
{
    public function __construct(
        private ConfigRepository $config,
        private PdfRendererInterface $renderer
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function render(string $html, array $options = []): string
    {
        $defaults = $this->config->get('pdf', []);
        if (!is_array($defaults)) {
            $defaults = [];
        }

        return $this->renderer->render($html, array_merge($defaults, $options));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function renderTemplate(PdfTemplateInterface $template, array $data = [], array $options = []): string
    {
        return $this->render($template->render($data), $options);
    }

    /**
     * @param array<string, string> $headers
     */
    public function download(string $pdf, string $filename, array $headers = []): Response
    {
        return $this->response($pdf, $filename, true, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function inline(string $pdf, string $filename = 'document.pdf', array $headers = []): Response
    {
        return $this->response($pdf, $filename, false, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function response(string $pdf, ?string $filename = null, bool $download = true, array $headers = []): Response
    {
        $disposition = $download ? 'attachment' : 'inline';
        if ($filename !== null && $filename !== '') {
            $safeName = str_replace(['"', "\r", "\n"], '', $filename);
            $disposition .= '; filename="' . $safeName . '"';
        }

        $headers = array_merge([
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($pdf),
            'Content-Disposition' => $disposition,
        ], $headers);

        return new Response(200, $headers, Stream::fromString($pdf));
    }
}


