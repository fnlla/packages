<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

if (!function_exists('app')) {
    function app(): \Fnlla\Core\Container
    {
        $app = $GLOBALS['Fnlla_app'] ?? null;
        if (!$app instanceof \Fnlla\Core\Container) {
            throw new RuntimeException(
                'Fnlla application container not initialized. Ensure bootstrap/app.php sets $GLOBALS[\'Fnlla_app\'].'
            );
        }
        return $app;
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = [], ?string $layout = null): \Fnlla\Http\Response
    {
        $app = app();
        if (function_exists('view_render')) {
            $path = function_exists('view_path') ? view_path($template) : '';
            if ($path !== '' && is_file($path)) {
                $html = view_render($template, $data, $layout);
                return \Fnlla\Http\Response::html($html);
            }
        }

        $html = \Fnlla\View\View::render($app, $template, $data, $layout);
        return \Fnlla\Http\Response::html($html);
    }
}

if (!function_exists('view_path')) {
    function view_path(string $template): string
    {
        $app = app();
        $config = $app->configRepository();
        $default = defined('APP_ROOT')
            ? APP_ROOT . '/resources/views'
            : getcwd() . '/resources/views';
        $viewsPath = rtrim((string) $config->get('views_path', $default), '/');
        return $viewsPath . '/' . ltrim($template, '/') . '.php';
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return \Fnlla\Support\absolute_url(app(), $path);
    }
}

if (!function_exists('site_url')) {
    function site_url(): string
    {
        return \Fnlla\Support\site_url(app());
    }
}

if (!function_exists('absolute_url')) {
    function absolute_url(string $path = ''): string
    {
        return \Fnlla\Support\absolute_url(app(), $path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return \Fnlla\Support\asset(app(), $path);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = [], bool $absolute = false): string
    {
        $app = app();
        $router = $app->make(\Fnlla\Http\Router::class);
        if (!$router instanceof \Fnlla\Http\Router) {
            return '';
        }
        $path = $router->url($name, $params);
        if (!$absolute) {
            return $path;
        }
        return \Fnlla\Support\absolute_url($app, $path);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        $app = app();
        if (!class_exists(\Fnlla\Csrf\CsrfTokenManager::class) || !interface_exists(\Fnlla\Session\SessionInterface::class)) {
            throw new RuntimeException('CSRF support is not available. Ensure the core CSRF and Session modules are enabled.');
        }
        $session = $app->make(\Fnlla\Session\SessionInterface::class);
        if (!$session instanceof \Fnlla\Session\SessionInterface) {
            throw new RuntimeException('Session service is not available.');
        }
        $manager = new \Fnlla\Csrf\CsrfTokenManager($session);
        return $manager->token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('errors')) {
    function errors(string $bag = 'default'): \Fnlla\Support\ErrorBag
    {
        static $cached = null;
        static $cachedBag = null;

        if ($cached === null) {
            $app = app();
            if (!interface_exists(\Fnlla\Session\SessionInterface::class)) {
                return new \Fnlla\Support\ErrorBag([], $bag);
            }
            $session = $app->make(\Fnlla\Session\SessionInterface::class);
            if (!$session instanceof \Fnlla\Session\SessionInterface) {
                return new \Fnlla\Support\ErrorBag([], $bag);
            }
            if (method_exists($session, 'getFlash')) {
                $cached = $session->getFlash('_Fnlla_errors', []);
                $cachedBag = $session->getFlash('_Fnlla_error_bag', 'default');
            } else {
                $cached = $session->get('_Fnlla_errors', []);
                $cachedBag = $session->get('_Fnlla_error_bag', 'default');
            }
        }

        if ($cachedBag !== $bag) {
            return new \Fnlla\Support\ErrorBag([], $bag);
        }

        $errors = is_array($cached) ? $cached : [];
        return new \Fnlla\Support\ErrorBag($errors, $bag);
    }
}

if (!function_exists('old')) {
    function old(?string $key = null, mixed $default = null): mixed
    {
        static $cached = null;

        if ($cached === null) {
            $app = app();
            if (!interface_exists(\Fnlla\Session\SessionInterface::class)) {
                return $default;
            }
            $session = $app->make(\Fnlla\Session\SessionInterface::class);
            if (!$session instanceof \Fnlla\Session\SessionInterface) {
                return $default;
            }
            if (method_exists($session, 'getFlash')) {
                $cached = $session->getFlash('_Fnlla_old', []);
            } else {
                $cached = $session->get('_Fnlla_old', []);
            }
        }

        if ($key === null) {
            return $cached;
        }

        if (!is_array($cached) || !array_key_exists($key, $cached)) {
            return $default;
        }

        return $cached[$key];
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to, int $status = 302): \Fnlla\Http\Response
    {
        return \Fnlla\Http\Response::redirect($to, $status);
    }
}

if (!function_exists('back')) {
    function back(int $status = 302): \Fnlla\Http\Response
    {
        $req = null;
        if (isset($GLOBALS['Fnlla_app']) && $GLOBALS['Fnlla_app'] instanceof \Fnlla\Core\Container) {
            $app = $GLOBALS['Fnlla_app'];
            if ($app->has(\Fnlla\Http\Request::class)) {
                $resolved = $app->make(\Fnlla\Http\Request::class);
                if ($resolved instanceof \Fnlla\Http\Request) {
                    $req = $resolved;
                }
            }
        }
        if (!$req instanceof \Fnlla\Http\Request) {
            $target = '/';
        } else {
            $target = \Fnlla\Http\RedirectTarget::fromReferer($req, '/');
        }
        return \Fnlla\Http\Response::redirect($target, $status);
    }
}

if (!function_exists('can')) {
    function can(string $ability, mixed $target = null, ?\Fnlla\Http\Request $request = null): bool
    {
        $app = app();
        if (!class_exists(\Fnlla\Authorization\Gate::class)) {
            return false;
        }

        $gate = $app->has(\Fnlla\Authorization\Gate::class)
            ? $app->make(\Fnlla\Authorization\Gate::class)
            : new \Fnlla\Authorization\Gate($app, new \Fnlla\Authorization\PolicyRegistry());

        if (!$gate instanceof \Fnlla\Authorization\Gate) {
            return false;
        }

        return $gate->allows($ability, $target, $request);
    }
}
