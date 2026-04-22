<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__);
$tests = [
    __DIR__ . '/smoke-security-headers.php',
    __DIR__ . '/smoke-cors.php',
    __DIR__ . '/smoke-rate-limit.php',
    __DIR__ . '/smoke-maintenance.php',
    __DIR__ . '/smoke-redirects.php',
    __DIR__ . '/smoke-cache-static.php',
    __DIR__ . '/smoke-forms.php',
];

foreach ($tests as $testPath) {
    if (!is_file($testPath)) {
        fwrite(STDERR, 'Missing ops smoke test: ' . $testPath . "\n");
        exit(1);
    }

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($testPath);
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $packageRoot);
    if (!is_resource($process)) {
        fwrite(STDERR, 'Unable to run ops smoke test: ' . $testPath . "\n");
        exit(1);
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $output = trim($stderr !== '' ? $stderr : $stdout);
        if ($output === '') {
            $output = 'exit ' . $exitCode;
        }
        fwrite(STDERR, $output . "\n");
        exit($exitCode);
    }
}

echo "Ops smoke tests OK\n";
