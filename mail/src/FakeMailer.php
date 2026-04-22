<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Mail;

use RuntimeException;

final class FakeMailer implements MailerInterface
{
    /** @var Message[] */
    private array $sent = [];

    public function send(Message $msg): void
    {
        $this->sent[] = clone $msg;
    }

    /** @return Message[] */
    public function sent(): array
    {
        return $this->sent;
    }

    public function assertSent(?callable $predicate = null): void
    {
        if ($predicate === null) {
            if ($this->sent === []) {
                throw new RuntimeException('Expected at least one mail to be sent.');
            }
            return;
        }

        foreach ($this->sent as $message) {
            if ($predicate($message) === true) {
                return;
            }
        }

        throw new RuntimeException('Expected mail matching predicate was not sent.');
    }
}
