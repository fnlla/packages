<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Pdf;

use Fnlla\Http\Router;
use Fnlla\Pdf\Http\PdfController;

final class PdfRoutes
{
    public static function register(Router $router, array $options = []): void
    {
        $prefix = (string) ($options['prefix'] ?? '/api/pdf');
        $middleware = $options['middleware'] ?? [];
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }

        $router->group(['prefix' => $prefix, 'middleware' => $middleware], function (Router $router): void {
            $router->get('/invoice', [PdfController::class, 'invoice'], 'pdf.invoice');
            $router->get('/pitch-deck', [PdfController::class, 'pitchDeck'], 'pdf.pitch-deck');
        });
    }
}


