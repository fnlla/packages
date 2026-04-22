**fnlla (finella) PDF**

HTML-to-PDF rendering using Dompdf, plus ready invoice and pitch-deck templates with a minimal API route helper.

**INSTALL**
```bash
composer require fnlla/pdf
```

**CONFIGURATION**
Create `config/pdf/pdf.php` in your app (see starter stub):
```php
return [
    'paper' => env('PDF_PAPER', 'A4'),
    'orientation' => env('PDF_ORIENTATION', 'portrait'),
    'default_font' => env('PDF_DEFAULT_FONT', 'DejaVu Sans'),
    'remote_enabled' => (bool) env('PDF_REMOTE_ENABLED', false),
    'download_name' => env('PDF_DOWNLOAD_NAME', 'document.pdf'),
];
```

**ROUTES (OPTIONAL)**
```php
use Fnlla\\Pdf\PdfRoutes;

return static function (Router $router): void {
    PdfRoutes::register($router, [
        'prefix' => '/api/pdf',
        'middleware' => [],
    ]);
};
```
This enables sample endpoints: `GET /api/pdf/invoice` and `GET /api/pdf/pitch-deck`.

**USING PDFMANAGER**
```php
use Fnlla\\Pdf\PdfManager;
use Fnlla\\Pdf\Templates\InvoiceTemplate;
use Fnlla\\Pdf\Templates\PitchDeckTemplate;
use Fnlla\\Http\Response;

public function invoice(PdfManager $pdf): Response
{
    $template = new InvoiceTemplate();
    $html = $template->render([
        'number' => 'INV-2026-0001',
        'client' => 'Acme Ltd',
        'currency' => 'USD',
        'items' => [
            ['label' => 'Website design', 'qty' => 1, 'price' => 2400],
            ['label' => 'Hosting (12 months)', 'qty' => 1, 'price' => 300],
        ],
    ]);

    $binary = $pdf->render($html);
    return $pdf->download($binary, 'invoice.pdf');
}
```

Pitch deck example:
```php
$template = new PitchDeckTemplate();
$html = $template->render([
    'project' => ['name' => 'Acme', 'tagline' => 'Next-gen workflow'],
    'company' => ['website' => 'https://acme.test'],
]);
$binary = $pdf->render($html);
return $pdf->download($binary, 'pitch-deck.pdf');
```

**NOTES**
**-** Dompdf supports a large subset of HTML/CSS but not every modern CSS feature.
**-** For remote images (URLs), set `PDF_REMOTE_ENABLED=true` and validate sources.

**TESTING**
```bash
php packages/pdf/tests/smoke.php
```
