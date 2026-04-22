<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail\Http;

use Fnlla\Core\ConfigRepository;
use Fnlla\Core\Container;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Mail\Address;
use Fnlla\Mail\Message;
use Fnlla\Webmail\MailboxClientInterface;
use Fnlla\Webmail\NullMailboxClient;
use Fnlla\Webmail\WebmailSettings;
use Fnlla\Webmail\WebmailSendJob;
use Fnlla\Webmail\WebmailSmtpClient;

final class WebmailController
{
    public function __construct(
        private MailboxClientInterface $mailbox,
        private WebmailSmtpClient $smtp,
        private WebmailSettings $settings,
        private ConfigRepository $config,
        private Container $app
    ) {
    }

    public function folders(Request $request): Response
    {
        if ($error = $this->imapAvailabilityError()) {
            return $error;
        }

        try {
            return Response::json(['data' => $this->mailbox->listFolders()]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 503);
        }
    }

    public function messages(Request $request): Response
    {
        if ($error = $this->imapAvailabilityError()) {
            return $error;
        }

        try {
            $folder = $this->resolveFolder($request);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 503);
        }

        $query = $request->getQueryParams();
        $limit = isset($query['limit']) ? (int) $query['limit'] : 50;
        $offset = isset($query['offset']) ? (int) $query['offset'] : 0;

        try {
            $items = $this->mailbox->listMessages($folder, $limit, $offset);
            return Response::json(['data' => $items]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 503);
        }
    }

    public function message(Request $request): Response
    {
        if ($error = $this->imapAvailabilityError()) {
            return $error;
        }

        try {
            $folder = $this->resolveFolder($request);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 503);
        }

        $uid = (int) $request->getAttribute('uid');
        if ($uid <= 0) {
            return Response::json(['error' => 'Invalid uid.'], 422);
        }

        try {
            $item = $this->mailbox->getMessage($folder, $uid);
            return Response::json(['data' => $item]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 503);
        }
    }

    public function delete(Request $request): Response
    {
        if ($error = $this->imapAvailabilityError()) {
            return $error;
        }

        try {
            $folder = $this->resolveFolder($request);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 503);
        }

        $uid = (int) $request->getAttribute('uid');
        if ($uid <= 0) {
            return Response::json(['error' => 'Invalid uid.'], 422);
        }

        try {
            $deleted = $this->mailbox->deleteMessage($folder, $uid);
            return Response::json(['deleted' => $deleted]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 503);
        }
    }

    public function send(Request $request): Response
    {
        $payload = $this->parse($request);
        $recipients = $this->normalizeRecipients($payload['to'] ?? '');
        $subject = (string) ($payload['subject'] ?? '');
        $text = (string) ($payload['text'] ?? $payload['body'] ?? '');
        $html = isset($payload['html']) ? (string) $payload['html'] : null;
        $from = (string) ($payload['from'] ?? '');
        $fromName = (string) ($payload['from_name'] ?? $payload['fromName'] ?? '');

        if ($recipients === [] || ($text === '' && ($html ?? '') === '')) {
            return Response::json(['error' => 'Missing recipient or body.'], 422);
        }

        $fromAddress = $from !== '' ? new Address($from, $fromName !== '' ? $fromName : null) : new Address('');
        $message = new Message(
            from: $fromAddress,
            to: $recipients,
            subject: $subject,
            text: $text,
            html: $html
        );

        try {
            if ($this->dispatchAsyncIfEnabled($message)) {
                return Response::json(['queued' => true], 202);
            }

            $this->smtp->send($message);
            return Response::json(['sent' => true], 201);
        } catch (\RuntimeException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 503);
        }
    }

    private function parse(Request $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }

    private function resolveFolder(Request $request): string
    {
        $query = $request->getQueryParams();
        $folder = (string) ($query['folder'] ?? '');
        if ($folder !== '') {
            return $folder;
        }

        $imap = $this->settings->imap();
        $default = (string) ($imap['folder'] ?? 'INBOX');
        return $default !== '' ? $default : 'INBOX';
    }

    private function imapAvailabilityError(): ?Response
    {
        if (!$this->mailbox instanceof NullMailboxClient) {
            return null;
        }

        try {
            $imap = $this->settings->imap();
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 503);
        }

        $host = (string) ($imap['host'] ?? '');
        $user = (string) ($imap['username'] ?? '');

        if ($host === '' || $user === '') {
            return Response::json(['error' => 'IMAP is not configured.'], 503);
        }

        if (!function_exists('imap_open')) {
            return Response::json(['error' => 'ext-imap is not enabled.'], 503);
        }

        return Response::json(['error' => 'IMAP client is not available.'], 503);
    }

    /**
     * @return Address[]
     */
    private function normalizeRecipients(mixed $value): array
    {
        $items = [];
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item)) {
                    $items[] = $item;
                }
            }
        } else {
            $items = explode(',', (string) $value);
        }

        $addresses = [];
        foreach ($items as $item) {
            $email = trim((string) $item);
            if ($email === '') {
                continue;
            }
            $addresses[] = new Address($email);
        }

        return $addresses;
    }

    private function dispatchAsyncIfEnabled(Message $message): bool
    {
        $async = (bool) $this->config->get('webmail.send_async', false);
        if (!$async) {
            return false;
        }

        if (!class_exists(\Fnlla\Queue\QueueManager::class)) {
            return false;
        }

        if (!$this->app->has(\Fnlla\Queue\QueueManager::class)) {
            return false;
        }

        $queue = $this->app->make(\Fnlla\Queue\QueueManager::class);
        if (!$queue instanceof \Fnlla\Queue\QueueManager) {
            return false;
        }

        $job = new WebmailSendJob(
            $message->from->email,
            $message->from->name,
            $this->addressesToStrings($message->to),
            $message->subject,
            $message->text ?? '',
            $message->html
        );

        $queue->dispatch($job);
        return true;
    }

    /**
     * @param Address[] $addresses
     * @return array<int, string>
     */
    private function addressesToStrings(array $addresses): array
    {
        $result = [];
        foreach ($addresses as $address) {
            if ($address instanceof Address && $address->email !== '') {
                $result[] = $address->email;
            }
        }
        return $result;
    }
}


