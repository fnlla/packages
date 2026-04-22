**FNLLA/SCHEDULER**

Lightweight scheduler for running periodic tasks via `schedule:run`.

**INSTALLATION**
```bash
composer require fnlla/scheduler
```

**SERVICE PROVIDER**
Auto-discovered provider:
**-** `Fnlla\\Scheduler\ScheduleServiceProvider`

**CONFIGURATION**
`config/scheduler/scheduler.php`:
**-** `timezone` (defaults to `UTC` or `APP_TIMEZONE`)
**-** `cache_path` (defaults to `storage/cache/schedule.json`)

**DEFINING SCHEDULES**
Create one of:
**-** `schedule.php`
**-** `app/Schedule.php`
**-** `config/schedule.php`

Example:
```php
<?php

use Fnlla\\Scheduler\Schedule;

return function (Schedule $schedule): void {
    $schedule->call('prune-cache', function (): void {
        // ...
    })->hourly();

    $schedule->command('reports:daily')->dailyAt('02:00');
};
```

**RUNNING THE SCHEDULER**
```bash
php bin/fnlla schedule:run
```

**NOTES**
Schedules support:
**-** `everySeconds`, `everyMinute`, `hourly`, `daily`, `dailyAt('02:00')`
**-** `runInBackground()` if `fnlla/queue` is available

Each run updates the last execution timestamp in the cache file.

**TESTING**
```bash
php tests/smoke.php
```
