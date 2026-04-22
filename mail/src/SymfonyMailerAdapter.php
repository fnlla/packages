<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Mail;

use Symfony\Component\Mailer\MailerInterface as SymfonyMailer;
use Symfony\Component\Mime\Address as SymfonyAddress;
use Symfony\Component\Mime\Email;

final class SymfonyMailerAdapter implements MailerInterface
{
    public function __construct(private SymfonyMailer $mailer)
    {
    }

    public function send(Message $msg): void
    {
        $email = new Email();

        $from = $this->mapAddress($msg->from);
        $email->from($from);

        if ($msg->to === []) {
            throw new \RuntimeException('Message has no recipients.');
        }

        foreach ($msg->to as $recipient) {
            $email->addTo($this->mapAddress($recipient));
        }

        $email->subject($msg->subject);

        if ($msg->text !== null) {
            $email->text($msg->text);
        }

        if ($msg->html !== null) {
            $email->html($msg->html);
        }

        $this->mailer->send($email);
    }

    private function mapAddress(Address $address): SymfonyAddress
    {
        return $address->name === null || $address->name === ''
            ? new SymfonyAddress($address->email)
            : new SymfonyAddress($address->email, $address->name);
    }
}