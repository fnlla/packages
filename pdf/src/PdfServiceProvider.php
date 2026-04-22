<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Pdf;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class PdfServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(PdfRendererInterface::class, function () use ($app): PdfRendererInterface {
            $config = $app->config()->get('pdf', []);
            if (!is_array($config)) {
                $config = [];
            }

            return new DompdfRenderer($config);
        });

        $app->singleton(PdfManager::class, function () use ($app): PdfManager {
            return new PdfManager($app->config(), $app->make(PdfRendererInterface::class));
        });
    }
}


