<?php

declare(strict_types=1);

use Fnlla\Core\ConfigRepository;
use Fnlla\Pdf\DompdfRenderer;
use Fnlla\Pdf\PdfManager;
use Fnlla\Pdf\Templates\PitchDeckTemplate;

if (!class_exists(DompdfRenderer::class)) {
    $candidates = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../tools/harness/vendor/autoload.php',
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            require $candidate;
            break;
        }
    }
}

$renderer = new DompdfRenderer(['paper' => 'A4']);
$manager = new PdfManager(new ConfigRepository(['pdf' => []]), $renderer);
$pdf = $manager->render('<html><body><h1>OK</h1></body></html>');

if (!is_string($pdf) || $pdf === '') {
    fwrite(STDERR, "FAIL: PDF output is empty\n");
    exit(1);
}

if (!str_starts_with($pdf, '%PDF')) {
    fwrite(STDERR, "FAIL: PDF header missing\n");
    exit(1);
}

$template = new PitchDeckTemplate();
$deckHtml = $template->render([
    'project' => ['name' => 'Acme', 'tagline' => 'Next-gen workflow'],
    'company' => ['website' => 'https://example.com'],
]);
$deckPdf = $manager->render($deckHtml);

if (!is_string($deckPdf) || $deckPdf === '' || !str_starts_with($deckPdf, '%PDF')) {
    fwrite(STDERR, "FAIL: Pitch deck PDF output invalid\n");
    exit(1);
}

echo "PDF smoke tests OK\n";
