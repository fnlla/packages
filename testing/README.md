**FNLLA/TESTING**

Lightweight testing helpers for fnlla (finella). Provides a base `TestCase` for HTTP feature tests and a simple CLI runner.

**INSTALLATION**
```bash
composer require --dev fnlla/testing
```

**RUNNING TESTS**
From your application:
```bash
vendor/bin/fnlla-test
```

**EXAMPLE**
```php
use Fnlla\\Testing\TestCase;

final class HealthTest extends TestCase
{
    public function testHealth(): void
    {
        $this->get('/health')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }
}
```

**FEATURES**
**-** `get()`, `post()`, `postJson()`
**-** `actingAs($user)`
**-** Assertions: `assertStatus`, `assertJson`, `assertRedirect`, `assertSessionHasErrors`
**-** In-memory SQLite and migrations via `refreshDatabase()`
**-** If no migrations are present, `refreshDatabase()` can load `database/schema.sql` or `database/schema.sqlite.sql`

**NOTES**
This package is intentionally minimal. It is designed for fast, dependency-free feature tests.
