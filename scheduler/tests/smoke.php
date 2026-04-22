<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Scheduler\ScheduleRegistry;
use Fnlla\Scheduler\ScheduleTask;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

$cache = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fnlla-schedule-' . uniqid() . '.json';

$registry = new ScheduleRegistry(null, $cache, 'UTC');

$ran = false;
$registry->call('ping', function () use (&$ran): void {
    $ran = true;
})->everySeconds(1);

$executed = $registry->runDue();
ok($ran === true, 'task executed');
ok(in_array('ping', $executed, true), 'task name returned');
ok(is_file($cache), 'cache file created');

$ran = false;
$executed = $registry->runDue();
ok($ran === false, 'task not re-run immediately');

// Unit-like: due now calculations.
$tz = new \DateTimeZone('UTC');
$now = new \DateTimeImmutable('2026-02-17 10:00:00', $tz);

$task = new ScheduleTask('minute', fn () => null);
$task->everyMinute();
ok($task->isDue($now, $now->getTimestamp() - 61), 'everyMinute due after 60s');
ok(!$task->isDue($now, $now->getTimestamp() - 30), 'everyMinute not due before 60s');

$task = new ScheduleTask('hourly', fn () => null);
$task->hourly();
ok($task->isDue($now, $now->getTimestamp() - 3601), 'hourly due after 1h');
ok(!$task->isDue($now, $now->getTimestamp() - 1800), 'hourly not due before 1h');

$task = new ScheduleTask('daily', fn () => null);
$task->dailyAt('02:00');
$firstRun = new \DateTimeImmutable('2026-02-17 02:00:00', $tz);
ok($task->isDue($firstRun, null), 'dailyAt due on first run');
ok(!$task->isDue(new \DateTimeImmutable('2026-02-17 03:00:00', $tz), $firstRun->getTimestamp()), 'dailyAt not due again same day');
ok($task->isDue(new \DateTimeImmutable('2026-02-18 02:00:00', $tz), $firstRun->getTimestamp()), 'dailyAt due next day');

@unlink($cache);

echo "Scheduler smoke tests OK\n";
