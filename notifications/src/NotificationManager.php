<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Notifications;

use DateTimeImmutable;
use Fnlla\Core\ConfigRepository;
use Fnlla\Mail\Address;
use Fnlla\Mail\MailerInterface;
use Fnlla\Mail\Message;
use RuntimeException;

final class NotificationManager
{
    public function __construct(
        private NotificationRepository $repository,
        private ConfigRepository $config,
        private MailerInterface $mailer,
        private SmsSenderInterface $smsSender
    ) {
    }

    public function send(
        string $channel,
        string $recipient,
        ?string $subject,
        string $body,
        ?string $html = null,
        array $metadata = []
    ): int {
        $channel = $channel !== '' ? $channel : (string) $this->config->get('notifications.default_channel', 'email');
        $channel = strtolower($channel);

        $id = $this->repository->create([
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => 'pending',
            'metadata' => $metadata,
        ]);

        try {
            if ($channel === 'email') {
                $this->sendEmail($recipient, $subject, $body, $html);
            } elseif ($channel === 'sms') {
                $this->smsSender->send($recipient, $body, $metadata);
            } else {
                throw new RuntimeException('Unsupported channel: ' . $channel);
            }

            $this->repository->updateStatus($id, 'sent', null, (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        } catch (\Throwable $e) {
            $this->repository->updateStatus($id, 'failed', $e->getMessage(), null);
        }

        return $id;
    }

    private function sendEmail(string $to, ?string $subject, string $text, ?string $html): void
    {
        $fromAddress = (string) $this->config->get('mail.from.address', 'noreply@example.test');
        $fromName = (string) $this->config->get('mail.from.name', 'Fnlla');

        $message = new Message(
            from: new Address($fromAddress, $fromName !== '' ? $fromName : null),
            to: [new Address($to)],
            subject: $subject ?? '',
            text: $text,
            html: $html
        );

        $this->mailer->send($message);
    }
}


