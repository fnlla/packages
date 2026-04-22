<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

use Fnlla\Auth\Http\AuthController;
use Fnlla\Auth\Http\PasswordResetController;
use Fnlla\Auth\Middleware\AuthMiddleware;
use Fnlla\Auth\Middleware\GuestMiddleware;
use Fnlla\Http\Router;

final class AuthRoutes
{
    /**
     * @param array<string, mixed> $options
     */
    public static function register(Router $router, array $options = []): void
    {
        $prefix = (string) ($options['prefix'] ?? '/auth');
        $middleware = $options['middleware'] ?? [];
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }

        $router->group(['prefix' => $prefix, 'middleware' => $middleware], function (Router $router): void {
            $router->get('/login', [AuthController::class, 'showLogin'], 'auth.login', [GuestMiddleware::class]);
            $router->post('/login', [AuthController::class, 'login'], 'auth.login.submit', [GuestMiddleware::class]);
            $router->post('/logout', [AuthController::class, 'logout'], 'auth.logout', [AuthMiddleware::class]);

            $router->get('/register', [AuthController::class, 'showRegister'], 'auth.register', [GuestMiddleware::class]);
            $router->post('/register', [AuthController::class, 'register'], 'auth.register.submit', [GuestMiddleware::class]);

            $router->get('/password/forgot', [PasswordResetController::class, 'showRequest'], 'auth.password.request', [GuestMiddleware::class]);
            $router->post('/password/email', [PasswordResetController::class, 'sendLink'], 'auth.password.email', [GuestMiddleware::class]);
            $router->get('/password/reset/{token}', [PasswordResetController::class, 'showReset'], 'auth.password.reset', [GuestMiddleware::class]);
            $router->post('/password/reset', [PasswordResetController::class, 'reset'], 'auth.password.update', [GuestMiddleware::class]);
        });
    }
}
