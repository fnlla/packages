<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Mail;

use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Fnlla\Support\Env;

final class MailManager
{
    private ?MailerInterface $mailer = null;
    private ?Address $defaultFrom = null;

    public function __construct(private array $config = [])
    {
    }

    public function mailer(): MailerInterface
    {
        if ($this->mailer !== null) {
            return $this->mailer;
        }

        $transport = $this->buildTransport();
        $symfonyMailer = new Mailer($transport);
        $this->mailer = new SymfonyMailerAdapter($symfonyMailer);

        return $this->mailer;
    }

    public function send(Message $msg): void
    {
        $msg = $this->applyDefaultFrom($msg);
        $this->mailer()->send($msg);
    }

    public function setMailer(MailerInterface $mailer): void
    {
        $this->mailer = $mailer;
    }

    public function defaultFrom(): ?Address
    {
        if ($this->defaultFrom !== null) {
            return $this->defaultFrom;
        }

        $address = (string) ($this->config['from']['address'] ?? $this->config['from_address'] ?? $this->env('MAIL_FROM_ADDRESS', ''));
        $name = $this->config['from']['name'] ?? $this->config['from_name'] ?? $this->env('MAIL_FROM_NAME', null);

        if ($address === '') {
            return null;
        }

        $this->defaultFrom = new Address($address, $name !== null ? (string) $name : null);
        return $this->defaultFrom;
    }

    private function applyDefaultFrom(Message $msg): Message
    {
        if ($msg->from->email !== '') {
            return $msg;
        }

        $default = $this->defaultFrom();
        if ($default === null) {
            throw new RuntimeException('Default from address is not configured.');
        }

        $msg->from = $default;
        return $msg;
    }

    private function buildTransport(): TransportInterface
    {
        $dsn = (string) ($this->config['dsn'] ?? $this->env('MAIL_DSN', ''));
        if ($dsn !== '') {
            return Transport::fromDsn($dsn);
        }

        $host = (string) ($this->config['host'] ?? $this->env('MAIL_HOST', ''));
        if ($host === '') {
            throw new RuntimeException('MAIL_HOST is not configured.');
        }

        $port = (string) ($this->config['port'] ?? $this->env('MAIL_PORT', ''));
        $username = (string) ($this->config['username'] ?? $this->env('MAIL_USERNAME', ''));
        $password = (string) ($this->config['password'] ?? $this->env('MAIL_PASSWORD', ''));
        $encryption = strtolower((string) ($this->config['encryption'] ?? $this->env('MAIL_ENCRYPTION', '')));

        $scheme = $encryption === 'ssl' || $encryption === 'smtps' ? 'smtps' : 'smtp';

        $auth = '';
        if ($username !== '') {
            $auth = rawurlencode($username);
            if ($password !== '') {
                $auth .= ':' . rawurlencode($password);
            }
            $auth .= '@';
        }

        $dsn = $scheme . '://' . $auth . $host;
        if ($port !== '') {
            $dsn .= ':' . $port;
        }

        if ($scheme === 'smtp' && $encryption !== '' && $encryption !== 'none') {
            $dsn .= '?encryption=' . rawurlencode($encryption);
        }

        return Transport::fromDsn($dsn);
    }

    private function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}
