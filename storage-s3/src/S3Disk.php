<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\StorageS3;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use RuntimeException;

final class S3Disk implements \Fnlla\Storage\DiskInterface
{
    private S3Client $client;
    private string $bucket;
    private string $prefix;
    private string $publicUrl;

    public function __construct(array $config = [])
    {
        $this->bucket = (string) ($config['bucket'] ?? '');
        if ($this->bucket === '') {
            throw new RuntimeException('S3 bucket is not configured.');
        }

        $this->prefix = trim((string) ($config['prefix'] ?? ''), '/');
        $this->publicUrl = rtrim((string) ($config['public_url'] ?? ''), '/');

        $client = $config['client'] ?? null;
        if ($client instanceof S3Client) {
            $this->client = $client;
            return;
        }

        $region = (string) ($config['region'] ?? 'eu-west-1');
        $args = [
            'version' => 'latest',
            'region' => $region,
        ];

        $key = (string) ($config['key'] ?? '');
        $secret = (string) ($config['secret'] ?? '');
        if ($key !== '' && $secret !== '') {
            $args['credentials'] = [
                'key' => $key,
                'secret' => $secret,
            ];
        }

        $endpoint = (string) ($config['endpoint'] ?? '');
        if ($endpoint !== '') {
            $args['endpoint'] = $endpoint;
            $args['use_path_style_endpoint'] = (bool) ($config['use_path_style'] ?? true);
        }

        $this->client = new S3Client($args);
    }

    public function path(string $path): string
    {
        return $this->key($path);
    }

    public function put(string $path, string $contents): bool
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $this->key($path),
                'Body' => $contents,
            ]);
            return true;
        } catch (S3Exception) {
            return false;
        }
    }

    public function putFile(string $path, string $sourcePath): bool
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $this->key($path),
                'SourceFile' => $sourcePath,
            ]);
            return true;
        } catch (S3Exception) {
            return false;
        }
    }

    public function get(string $path): ?string
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->key($path),
            ]);
            $body = $result['Body'] ?? null;
            if ($body === null) {
                return null;
            }
            return (string) $body;
        } catch (S3Exception) {
            return null;
        }
    }

    public function exists(string $path): bool
    {
        if (method_exists($this->client, 'doesObjectExist')) {
            return $this->client->doesObjectExist($this->bucket, $this->key($path));
        }

        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $this->key($path),
            ]);
            return true;
        } catch (S3Exception) {
            return false;
        }
    }

    public function delete(string $path): bool
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $this->key($path),
            ]);
            return true;
        } catch (S3Exception) {
            return false;
        }
    }

    public function url(string $path): string
    {
        $key = $this->key($path);
        if ($this->publicUrl !== '') {
            return $this->publicUrl . '/' . ltrim($key, '/');
        }
        return $this->client->getObjectUrl($this->bucket, $key);
    }

    private function key(string $path): string
    {
        $path = ltrim($path, '/');
        if ($this->prefix === '') {
            return $path;
        }
        return $this->prefix . '/' . $path;
    }
}
