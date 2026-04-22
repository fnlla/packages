<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\View;

use Fnlla\Core\Container;
use RuntimeException;

final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function hasShared(string $key): bool
    {
        return array_key_exists($key, self::$shared);
    }

    public static function render(Container $app, string $template, array $data = [], ?string $layout = null): string
    {
        $config = $app->configRepository();
        $default = defined('APP_ROOT')
            ? APP_ROOT . '/resources/views'
            : getcwd() . '/resources/views';
        $viewsPath = rtrim((string) $config->get('views_path', $default), '/');
        if (!is_dir($viewsPath)) {
            throw new RuntimeException('Views path not found: ' . $viewsPath);
        }
        $merged = array_merge(self::$shared, ['app' => $app], $data);
        $templatePath = ltrim($template, '/');
        $file = $viewsPath . '/' . $templatePath . '.php';
        if (is_file($file) && function_exists('view_render')) {
            return (string) view_render($templatePath, $merged, $layout);
        }

        if (is_file($file)) {
            extract($merged, EXTR_SKIP);
            ob_start();
            require $file;
            $content = ob_get_clean();
        } else {
            return '';
        }

        if ($layout === null) {
            return $content;
        }

        $layoutFile = $viewsPath . '/' . ltrim($layout, '/') . '.php';
        if (!is_file($layoutFile)) {
            return $content;
        }

        ob_start();
        require $layoutFile;
        return ob_get_clean();
    }
}




