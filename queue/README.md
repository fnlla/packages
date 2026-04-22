**FNLLA/QUEUE**

Queue module with synchronous, database, and Redis drivers.

**INSTALLATION**
```bash
composer require fnlla/queue
```

**SERVICE PROVIDER**
Auto-discovered provider:
**-** `Fnlla\\Queue\QueueServiceProvider`

**CONFIGURATION**
`config/queue/queue.php`
**-** `driver` (`sync`, `database`, or `redis`)
**-** `database.table` (default `queue_jobs`)
**-** `database.failed_table` (default `queue_failed_jobs`)
**-** `redis.queue` (default `default`)
**-** `redis.prefix` (default `fnlla:queue:`)

**USAGE**
```php
use Fnlla\\Queue\QueueManager;

$queue = $app->make(QueueManager::class);
$queue->dispatch(new class implements \Fnlla\\Queue\JobInterface {
    public function handle(\Fnlla\\Core\Container $app): void
    {
        // do work
    }
});
```

**WORKER**
```php
use Fnlla\\Queue\QueueWorker;

$worker = new QueueWorker($queue, $app);
$worker->work(1);
```

**NOTES**
The sync driver executes jobs immediately. The database and Redis drivers store jobs and can be
processed by `php bin/fnlla queue:work`.
The Redis driver requires the `ext-redis` PHP extension.

**TESTING**
```bash
php tests/smoke.php
```
