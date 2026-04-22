<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\StorageS3\S3Disk;

if (!class_exists(\Aws\S3\S3Client::class)) {
    echo "storage-s3 skip (aws sdk missing)\n";
    exit(0);
}

$config = [
    'bucket' => 'fnlla-test-bucket',
    'region' => 'eu-west-1',
    'key' => 'test',
    'secret' => 'test',
    'endpoint' => 'http://localhost:9000',
    'use_path_style' => true,
];

try {
    $disk = new S3Disk($config);
    if (!$disk instanceof S3Disk) {
        throw new RuntimeException('S3Disk instantiation failed.');
    }
    echo "storage-s3 ok\n";
} catch (Throwable $e) {
    fwrite(STDERR, "storage-s3 failed: " . $e->getMessage() . "\n");
    exit(1);
}
