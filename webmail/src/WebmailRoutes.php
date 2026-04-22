<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail;

use Fnlla\Http\Router;
use Fnlla\Webmail\Http\WebmailController;
use Fnlla\Webmail\Http\WebmailSettingsController;
use Fnlla\Webmail\Http\WebmailTestController;

final class WebmailRoutes
{
    /**
     * @param array<string, mixed> $options
     */
    public static function register(Router $router, array $options = []): void
    {
        $prefix = (string) ($options['prefix'] ?? '/api/webmail');
        $middleware = $options['middleware'] ?? [];
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }
        if (isset($options['rate']) && is_string($options['rate']) && trim($options['rate']) !== '') {
            $rate = trim($options['rate']);
            if (!str_starts_with($rate, 'rate:')) {
                $rate = 'rate:' . $rate;
            }
            $middleware[] = $rate;
        }

        $router->group(['prefix' => $prefix, 'middleware' => $middleware], function (Router $router): void {
            $router->get('/settings', [WebmailSettingsController::class, 'show'], 'webmail.settings');
            $router->add('PUT', '/settings', [WebmailSettingsController::class, 'update'], 'webmail.settings.update');
            $router->post('/test', [WebmailTestController::class, 'test'], 'webmail.test');
            $router->get('/folders', [WebmailController::class, 'folders'], 'webmail.folders');
            $router->get('/messages', [WebmailController::class, 'messages'], 'webmail.messages');
            $router->get('/messages/{uid}', [WebmailController::class, 'message'], 'webmail.message');
            $router->add('DELETE', '/messages/{uid}', [WebmailController::class, 'delete'], 'webmail.message.delete');
            $router->post('/send', [WebmailController::class, 'send'], 'webmail.send');
        });
    }
}

