<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Mail;

use Fnlla\Core\Container;
use RuntimeException;
use Throwable;

final class Mail
{
    private static ?FakeMailer $fake = null;

    public static function fake(): FakeMailer
    {
        $fake = new FakeMailer();
        self::$fake = $fake;

        $app = self::app();
        if ($app instanceof Container) {
            $app->instance(MailerInterface::class, $fake);

            if ($app->has(MailManager::class)) {
                $manager = $app->make(MailManager::class);
                if ($manager instanceof MailManager) {
                    $manager->setMailer($fake);
                }
            }
        }

        return $fake;
    }

    public static function assertSent(?callable $predicate = null): void
    {
        self::fakeOrFail()->assertSent($predicate);
    }

    /** @return Message[] */
    public static function sent(): array
    {
        return self::fakeOrFail()->sent();
    }

    public static function send(Message $message): void
    {
        if (self::$fake instanceof FakeMailer) {
            self::$fake->send($message);
            return;
        }

        $app = self::app();
        if ($app instanceof Container && $app->has(MailManager::class)) {
            $manager = $app->make(MailManager::class);
            if ($manager instanceof MailManager) {
                $manager->send($message);
                return;
            }
        }

        $mailer = self::mailer();
        $mailer->send($message);
    }

    private static function mailer(): MailerInterface
    {
        $app = self::app();
        if ($app instanceof Container && $app->has(MailerInterface::class)) {
            $mailer = $app->make(MailerInterface::class);
            if ($mailer instanceof MailerInterface) {
                return $mailer;
            }
        }

        throw new RuntimeException('Mailer is not available. Ensure fnlla/mail is installed and the provider is registered.');
    }

    private static function app(): ?Container
    {
        if (!function_exists('app')) {
            return null;
        }

        try {
            $app = app();
        } catch (Throwable) {
            return null;
        }

        return $app;
    }

    private static function fakeOrFail(): FakeMailer
    {
        if (!self::$fake instanceof FakeMailer) {
            throw new RuntimeException('Mail is not faked. Call Mail::fake() before asserting.');
        }

        return self::$fake;
    }
}
