<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Docs;

final class DocsSlug
{
    public static function normalize(string $slug): ?string
    {
        $slug = trim(str_replace('\\', '/', $slug), '/');
        if ($slug === '' || str_contains($slug, '..')) {
            return null;
        }

        $segments = array_filter(explode('/', $slug), static fn (string $segment): bool => $segment !== '');
        $normalized = [];

        foreach ($segments as $segment) {
            $segment = strtolower($segment);
            $segment = preg_replace('/[^a-z0-9._-]+/', '-', $segment) ?? '';
            $segment = trim($segment, '-');
            if ($segment === '') {
                continue;
            }
            $normalized[] = $segment;
        }

        if ($normalized === []) {
            return null;
        }

        return implode('/', $normalized);
    }

    public static function normalizeScope(string $scope): ?string
    {
        $scope = trim(strtolower($scope));
        if ($scope === '') {
            return '';
        }
        if (str_contains($scope, '/') || str_contains($scope, '\\')) {
            return null;
        }
        $scope = preg_replace('/[^a-z0-9._-]+/', '-', $scope) ?? '';
        $scope = trim($scope, '-');
        return $scope === '' ? null : $scope;
    }

    public static function build(string $scope, string $slug): ?string
    {
        $slug = self::normalize($slug);
        if ($slug === null) {
            return null;
        }
        $scope = self::normalizeScope($scope);
        if ($scope === null) {
            return null;
        }
        if ($scope === '') {
            return $slug;
        }
        return $scope . '/' . $slug;
    }
}


