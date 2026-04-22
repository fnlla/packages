<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Content\ContentRepository;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fnlla-content-tests';
@mkdir($tmp, 0775, true);
$file = $tmp . DIRECTORY_SEPARATOR . 'services.md';
file_put_contents($file, "---\n{\"title\":\"Services\"}\n---\nHello");

$repo = new ContentRepository($tmp);
$item = $repo->get('services');

ok($item !== null, 'Content item loaded');
ok($item->get('title') === 'Services', 'Content meta parsed');

echo "Content smoke tests OK\n";
