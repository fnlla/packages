<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\MailPreview;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class MailPreviewServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        // Routes are registered manually via MailPreviewRoutes.
    }
}
