**FNLLA/DEPLOY**

Deployment utilities for fnlla (finella).

**INSTALLATION**
```bash
composer require fnlla/deploy
```

**COMMANDS**
**-** `deploy:health` - basic environment checks for a deploy target.
**-** `deploy:warmup` - runs cache/provider warmup when available.

**REGISTER COMMANDS**
Add the commands to your `config/console/console.php`:
```php
return [
    'commands' => [
        Fnlla\\Deploy\Commands\DeployHealthCommand::class,
        Fnlla\\Deploy\Commands\DeployWarmupCommand::class,
    ],
];
```

**TESTING**
```bash
php tests/smoke.php
```
