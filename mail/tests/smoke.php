<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Mail\Address;
use Fnlla\Mail\MailManager;
use Fnlla\Mail\Message;
use Fnlla\Mail\SymfonyMailerAdapter;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$transport = Transport::fromDsn('null://null');
$mailer = new Mailer($transport);
$adapter = new SymfonyMailerAdapter($mailer);

$msg = new Message(
    new Address('sender@example.test', 'Sender'),
    [new Address('first@example.test'), new Address('second@example.test', 'Second')],
    'Smoke subject',
    'Plain text',
    '<p>HTML body</p>'
);

$adapter->send($msg);
ok(true, 'adapter send');

$manager = new MailManager([
    'dsn' => 'null://null',
    'from' => [
        'address' => 'noreply@example.test',
        'name' => 'Fnlla',
    ],
]);

$manager->send(new Message(
    new Address(''),
    [new Address('receiver@example.test')],
    'Default from',
    'Text body'
));

ok(true, 'manager send');

echo "Mail smoke tests OK\n";
