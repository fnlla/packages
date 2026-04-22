<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Core\ConfigRepository;
use Fnlla\Database\ConnectionManager;
use Fnlla\Mail\MailerInterface;
use Fnlla\Mail\Message;
use Fnlla\Notifications\NotificationManager;
use Fnlla\Notifications\NotificationRepository;
use Fnlla\Notifications\NotificationsSchema;
use Fnlla\Notifications\NullSmsSender;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

$connections = new ConnectionManager([
    'driver' => 'sqlite',
    'path' => ':memory:',
]);

$pdo = $connections->connection();
NotificationsSchema::ensure($pdo);

$repo = new NotificationRepository($connections);
$mailer = new class implements MailerInterface {
    public array $sent = [];
    public function send(Message $msg): void
    {
        $this->sent[] = $msg;
    }
};

$config = new ConfigRepository([
    'notifications' => ['default_channel' => 'email'],
    'mail' => ['from' => ['address' => 'noreply@example.test', 'name' => 'Fnlla']],
]);

$manager = new NotificationManager($repo, $config, $mailer, new NullSmsSender());
$id = $manager->send('email', 'user@example.test', 'Test', 'Hello');

$item = $repo->find($id);
ok($item !== null, 'notification stored');
ok(($item['status'] ?? '') === 'sent', 'notification marked as sent');

echo "Notifications smoke tests OK\n";
