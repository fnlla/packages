<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Tenancy;

use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;

final class TenantResolver implements TenantResolverInterface
{
    public function __construct(private array $config = [])
    {
    }

    public function resolve(ServerRequestInterface $request): ?string
    {
        $resolver = strtolower(trim((string) ($this->config['resolver'] ?? 'header')));
        if ($resolver === '') {
            $resolver = 'header';
        }

        return match ($resolver) {
            'host' => $this->resolveHost($request),
            'path' => $this->resolvePath($request),
            'auto' => $this->resolveAuto($request),
            default => $this->resolveHeader($request),
        };
    }

    private function resolveAuto(ServerRequestInterface $request): ?string
    {
        $header = $this->resolveHeader($request);
        if ($header !== null) {
            return $header;
        }

        $host = $this->resolveHost($request);
        if ($host !== null) {
            return $host;
        }

        return $this->resolvePath($request);
    }

    private function resolveHeader(ServerRequestInterface $request): ?string
    {
        $header = (string) ($this->config['header'] ?? 'X-Tenant-Id');
        $value = '';
        if (method_exists($request, 'getHeaderLine')) {
            $value = trim((string) $request->getHeaderLine($header));
        }

        return $value !== '' ? $value : null;
    }

    private function resolveHost(ServerRequestInterface $request): ?string
    {
        $host = '';
        if (method_exists($request, 'getUri')) {
            $host = (string) $request->getUri()->getHost();
        }
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        $hostConfig = $this->config['host'] ?? [];
        if (!is_array($hostConfig)) {
            $hostConfig = [];
        }

        $map = $hostConfig['map'] ?? [];
        if (is_array($map) && isset($map[$host]) && is_string($map[$host])) {
            $mapped = trim($map[$host]);
            return $mapped !== '' ? $mapped : null;
        }

        $baseDomain = strtolower(trim((string) ($hostConfig['base_domain'] ?? '')));
        if ($baseDomain === '') {
            return null;
        }

        if ($host === $baseDomain) {
            return null;
        }

        if (str_ends_with($host, '.' . $baseDomain)) {
            $sub = substr($host, 0, -strlen('.' . $baseDomain));
            $sub = trim($sub, '.');
            return $sub !== '' ? $sub : null;
        }

        return null;
    }

    private function resolvePath(ServerRequestInterface $request): ?string
    {
        if (!method_exists($request, 'getUri')) {
            return null;
        }

        $pathConfig = $this->config['path'] ?? [];
        if (!is_array($pathConfig)) {
            $pathConfig = [];
        }

        $segment = (int) ($pathConfig['segment'] ?? 1);
        $segment = max(1, $segment);

        $path = (string) $request->getUri()->getPath();
        $path = trim($path, '/');
        if ($path === '') {
            return null;
        }

        $parts = explode('/', $path);
        $index = $segment - 1;
        $value = $parts[$index] ?? '';
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
