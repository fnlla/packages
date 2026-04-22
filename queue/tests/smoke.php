<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Queue\JobInterface;
use Fnlla\Queue\QueueManager;
use Fnlla\Core\Container;
use Fnlla\Database\ConnectionManager;
use Fnlla\Queue\DatabaseQueue;
use Fnlla\Queue\QueueWorker;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

final class FileJob implements JobInterface
{
    public function __construct(private string $path)
    {
    }

    public function handle(Container $app): void
    {
        file_put_contents($this->path, 'ok');
    }
}

final class FailJob implements JobInterface
{
    public function handle(Container $app): void
    {
        throw new \RuntimeException('boom');
    }
}

final class NoopJob implements JobInterface
{
    public function handle(Container $app): void
    {
    }
}

$container = new Container();

$manager = new QueueManager([
    'driver' => 'sync',
], fn () => $container);

$tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fnlla-queue-smoke-' . uniqid() . '.txt';

$job = new FileJob($tempFile);

$manager->dispatch($job);

ok(is_file($tempFile) === true, 'job wrote file');

@unlink($tempFile);

// Database driver
$dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fnlla-queue-db-' . uniqid() . '.sqlite';
$connections = new ConnectionManager([
    'driver' => 'sqlite',
    'path' => $dbPath,
]);
$container->instance(ConnectionManager::class, $connections);

$dbManager = new QueueManager([
    'driver' => 'database',
    'payload_secret' => 'queue-test-secret',
    'allowed_job_classes' => ['*'],
    'database' => [
        'table' => 'queue_jobs',
        'failed_table' => 'queue_failed_jobs',
    ],
], fn () => $container);

$queue = $dbManager->queue();
ok($queue instanceof DatabaseQueue, 'database driver resolved');

$dbFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fnlla-queue-db-job-' . uniqid() . '.txt';
$dbJob = new FileJob($dbFile);

$dbManager->dispatch($dbJob);
$worker = new QueueWorker($queue, $container);
$processed = $worker->work(1, 0);
ok($processed === 1, 'worker processed job');
ok(is_file($dbFile), 'database job wrote file');

@unlink($dbFile);

$failingJob = new FailJob();

$dbManager->dispatch($failingJob);
$worker = new QueueWorker($queue, $container, 3, [5, 10], 60);
$worker->work(1, 0);

$pdo = new \PDO('sqlite:' . $dbPath);
$row = $pdo->query('SELECT id, attempts, available_at FROM queue_jobs')->fetch(PDO::FETCH_ASSOC);
ok(is_array($row), 'job released for retry');
ok((int) $row['attempts'] === 1, 'attempts incremented');
ok((int) $row['available_at'] > time(), 'backoff sets available_at in the future');

$pdo->prepare('UPDATE queue_jobs SET available_at = ? WHERE id = ?')->execute([time(), (int) $row['id']]);
$worker->work(1, 0);

$row = $pdo->query('SELECT id, attempts, available_at FROM queue_jobs')->fetch(PDO::FETCH_ASSOC);
ok(is_array($row), 'job retried second time');
ok((int) $row['attempts'] === 2, 'attempts incremented again');
ok((int) $row['available_at'] > time(), 'second backoff applied');

$pdo->prepare('UPDATE queue_jobs SET available_at = ? WHERE id = ?')->execute([time(), (int) $row['id']]);
$worker->work(1, 0);

$failedCount = (int) $pdo->query('SELECT COUNT(*) FROM queue_failed_jobs')->fetchColumn();
ok($failedCount === 1, 'failed job recorded after max attempts');

$remaining = (int) $pdo->query('SELECT COUNT(*) FROM queue_jobs')->fetchColumn();
ok($remaining === 0, 'job removed from queue after failure');

// Tamper test (signature mismatch)
$tamperManager = new QueueManager([
    'driver' => 'database',
    'payload_secret' => 'queue-test-secret',
    'allowed_job_classes' => ['*'],
    'database' => [
        'table' => 'queue_jobs',
        'failed_table' => 'queue_failed_jobs',
    ],
], fn () => $container);

$tamperQueue = $tamperManager->queue();

$tamperJob = new NoopJob();

$tamperManager->dispatch($tamperJob);
$row = $pdo->query('SELECT id, payload FROM queue_jobs')->fetch(PDO::FETCH_ASSOC);
ok(is_array($row), 'tamper test job queued');

$pdo->prepare('UPDATE queue_jobs SET payload = ? WHERE id = ?')->execute(['tampered', (int) $row['id']]);

$worker = new QueueWorker($tamperQueue, $container);
$worker->work(1, 0);

$failedCount = (int) $pdo->query('SELECT COUNT(*) FROM queue_failed_jobs')->fetchColumn();
ok($failedCount >= 2, 'tampered job moved to failed jobs');

$remaining = (int) $pdo->query('SELECT COUNT(*) FROM queue_jobs')->fetchColumn();
ok($remaining === 0, 'tampered job removed from queue');

// Whitelist test (reject unknown class)
$whitelistManager = new QueueManager([
    'driver' => 'database',
    'payload_secret' => 'queue-test-secret',
    'allowed_job_classes' => ['AllowedJob'],
    'database' => [
        'table' => 'queue_jobs_sec',
        'failed_table' => 'queue_failed_jobs_sec',
    ],
], fn () => $container);

$whitelistQueue = $whitelistManager->queue();

$notAllowedJob = new NoopJob();

$whitelistManager->dispatch($notAllowedJob);
$worker = new QueueWorker($whitelistQueue, $container);
$worker->work(1, 0);

$failedCount = (int) $pdo->query('SELECT COUNT(*) FROM queue_failed_jobs_sec')->fetchColumn();
ok($failedCount === 1, 'unknown job class rejected by whitelist');

$remaining = (int) $pdo->query('SELECT COUNT(*) FROM queue_jobs_sec')->fetchColumn();
ok($remaining === 0, 'unknown job removed from queue');

@unlink($dbPath);

echo "Queue smoke tests OK\n";
