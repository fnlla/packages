**FNLLA/MONITORING**

Monitoring and observability utilities for fnlla (finella).

**INSTALLATION**
```bash
composer require fnlla/monitoring
```

**CONFIGURATION**
Create `config/monitoring/monitoring.php` and set `.env`:
```
MONITORING_ENABLED=1
MONITORING_PUBLIC=0
MONITORING_ACCESS_TOKEN=change-me
MONITORING_RECENT_LIMIT=30
```

**ROUTES**
The package exposes `GET /metrics` (JSON). Protect it with a token or keep it private.

**MIDDLEWARE**
Enable the metrics middleware in `config/http/http.php` via `MONITORING_ENABLED=1`.

**OBSERVABILITY DATA**
**-** Request totals, status counters, and latency aggregates.
**-** Recent request traces (`method`, `path`, `status`, `duration`, request IDs).
**-** Debugbar-derived metrics when debugbar is enabled (`queries`, `errors`, `slow queries`, request time, memory).
**-** Profiler snapshots (`db_ms`, `db_queries`, cache hits/misses).
