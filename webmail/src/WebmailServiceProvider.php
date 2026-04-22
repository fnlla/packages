<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail;

use Fnlla\Core\Container;
use Fnlla\Settings\SettingsStore;
use Fnlla\Support\ServiceProvider;

final class WebmailServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(WebmailCipher::class, function (): WebmailCipher {
            return new WebmailCipher();
        });

        $app->singleton(WebmailSettingsKeys::class, function () use ($app): WebmailSettingsKeys {
            return new WebmailSettingsKeys($app->config());
        });

        $app->singleton(WebmailSettings::class, function () use ($app): WebmailSettings {
            $store = null;
            if ($app->has(SettingsStore::class)) {
                $store = $app->make(SettingsStore::class);
            }

            return new WebmailSettings(
                $app->config(),
                $store,
                $app->make(WebmailCipher::class),
                $app->make(WebmailSettingsKeys::class)
            );
        });

        $app->singleton(WebmailSmtpClient::class, function () use ($app): WebmailSmtpClient {
            return new WebmailSmtpClient($app->make(WebmailSettings::class));
        });

        if ($app->has(MailboxClientInterface::class)) {
            return;
        }

        $app->singleton(MailboxClientInterface::class, function () use ($app): MailboxClientInterface {
            $settings = $app->make(WebmailSettings::class);
            $imap = $settings->imap();

            $host = (string) ($imap['host'] ?? '');
            $user = (string) ($imap['username'] ?? '');
            if ($host === '' || $user === '' || !function_exists('imap_open')) {
                return new NullMailboxClient();
            }

            return new ImapMailboxClient($imap);
        });
    }
}


