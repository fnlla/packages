<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\MailPreview\Http;

use Fnlla\Http\Request;
use Fnlla\Http\Response;

final class MailPreviewController
{
    public function show(Request $request): Response
    {
        $query = $request->getQueryParams();
        $template = isset($query['template']) ? (string) $query['template'] : 'mail/preview';
        $template = ltrim($template, '/');

        if (!str_starts_with($template, 'mail/')) {
            $template = 'mail/preview';
        }

        if (str_contains($template, '..')) {
            $template = 'mail/preview';
        }

        return view($template, [
            'subject' => 'Mail preview',
            'preheader' => 'This is a sample preview email.',
            'headline' => 'Welcome to Fnlla',
            'body' => 'This is a placeholder email template for local preview.',
            'cta_label' => 'Open dashboard',
            'cta_url' => '/',
        ]);
    }
}
