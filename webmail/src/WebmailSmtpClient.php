<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail;

use Fnlla\Mail\Address;
use Fnlla\Mail\MailManager;
use Fnlla\Mail\Message;

final class WebmailSmtpClient
{
    public function __construct(private WebmailSettings $settings)
    {
    }

    public function send(Message $message): void
    {
        $config = $this->settings->smtp();
        $from = $this->resolveFrom($config);

        if ($message->from->email === '') {
            $message->from = $from;
        }

        $manager = new MailManager($this->buildMailConfig($config, $from));
        $manager->send($message);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveFrom(array $config): Address
    {
        $address = (string) ($config['from_address'] ?? '');
        $name = (string) ($config['from_name'] ?? '');
        return new Address($address, $name !== '' ? $name : null);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildMailConfig(array $config, Address $from): array
    {
        return [
            'dsn' => (string) ($config['dsn'] ?? ''),
            'host' => (string) ($config['host'] ?? ''),
            'port' => (int) ($config['port'] ?? 0),
            'username' => (string) ($config['username'] ?? ''),
            'password' => (string) ($config['password'] ?? ''),
            'encryption' => (string) ($config['encryption'] ?? ''),
            'from' => [
                'address' => $from->email,
                'name' => $from->name,
            ],
        ];
    }
}

