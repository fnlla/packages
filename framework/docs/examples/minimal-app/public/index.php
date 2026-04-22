<?php

declare(strict_types=1);

use Fnlla\Contracts\Http\KernelInterface;
use Fnlla\Http\Request;

require __DIR__ . '/../vendor/autoload.php';

$kernel = require __DIR__ . '/../bootstrap/app.php';
if (!$kernel instanceof KernelInterface) {
    http_response_code(500);
    echo 'Bootstrap must return a KernelInterface.';
    exit(1);
}

$request = Request::fromGlobals();
$response = $kernel->handle($request);
$response->send();
