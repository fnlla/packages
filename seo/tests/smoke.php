<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Seo\SeoManager;
use Fnlla\Runtime\RequestContext;
use Fnlla\Runtime\ResetManager;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$seo = new SeoManager();
$seo->title('TechAyo')->description('Services');
$html = $seo->render();

ok(str_contains($html, '<title>'), 'SEO title rendered');

$context = new RequestContext(new ResetManager(), 'req-1', microtime(true), null, 'nonce-test');
$context->begin();

$jsonLd = (new SeoManager())
    ->jsonLd(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Fnlla'])
    ->renderJsonLd();

$context->end();

ok(str_contains($jsonLd, 'type="application/ld+json"'), 'JSON-LD script rendered');
ok(str_contains($jsonLd, 'nonce="nonce-test"'), 'JSON-LD script nonce rendered');

echo "SEO smoke tests OK\n";
