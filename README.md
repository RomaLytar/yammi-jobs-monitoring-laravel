# yammi-jobs-monitoring-laravel

> **Background jobs monitor for Laravel — a lightweight alternative to Horizon.**
> Works without Redis. Driver-agnostic. Built for small and mid-size projects
> that don't need (or can't run) the full Horizon stack.

> ⚠️ **Status: pre-alpha / idea stage.** APIs, schema and UI will change. Not
> production ready yet. See the [Roadmap](#roadmap) below.

---

## Why

[Laravel Horizon](https://laravel.com/docs/horizon) is great, but it has two
hard constraints:

1. It is **Redis-only**. You can't use it with SQS, database or sync drivers.
2. It is **heavy** for small projects — a separate dashboard, supervisors,
   metrics store, all running just to see "did my jobs run".

Many projects just want to answer:

- Did my jobs run?
- Which ones failed, when, and why?
- How long do they take?
- How many times were they retried?
- Notify me when something explodes.

This package does exactly that — and nothing more — across **any** Laravel
queue driver.

---

## Core idea

Instead of monitoring the queue backend, we monitor the **job lifecycle**
via Laravel's built-in events:

- `Illuminate\Queue\Events\JobProcessing`
- `Illuminate\Queue\Events\JobProcessed`
- `Illuminate\Queue\Events\JobFailed`
- `Illuminate\Queue\Events\JobExceptionOccurred`

These events fire for **every** queue driver (redis, database, sqs, beanstalkd,
sync), so the core observability layer is universal. Driver-specific extras
(like queue depth) are exposed through a small driver interface and
**gracefully degrade** when the backend can't provide them.

---

## Features

### Universal (works on all drivers)

- ✅ Per-job lifecycle tracking: `pending → processing → processed | failed`
- ✅ Execution time (`started_at` / `finished_at` / `duration_ms`)
- ✅ **Retry count** and per-attempt history
- ✅ Exception class, message and stack trace for failures
- ✅ Connection / queue / job class breakdown
- ✅ Failure-rate metrics
- ✅ Notifications on failure (mail / slack / telegram — pluggable)
- ✅ Minimal web UI (Blade) — jobs list, job detail, failures

### Driver-dependent (graceful degradation)

| Feature              | redis | database | sqs              | sync |
|----------------------|:-----:|:--------:|:----------------:|:----:|
| Lifecycle tracking   |   ✅   |    ✅     |        ✅         |  ⚠️   |
| Retry count          |   ✅   |    ✅     |        ✅         |  ➖   |
| Queue size           |   ✅   |    ✅     | ⚠️ (AWS API only) |  ➖   |
| Inspect payload      |   ✅   |    ✅     |        ❌         |  ➖   |

When a driver can't provide a metric, the corresponding API returns `null`
and the UI hides the widget. No crashes, no fake numbers.

---

## Requirements

- **PHP** `^8.1` (we use native enums and `readonly` properties)
- **Laravel** `^9.0 || ^10.0 || ^11.0` (Laravel 9 still works because it
  tolerates PHP 8.1+)
- **Composer** `2.x`
- A storage backend for the monitor's own table — any Laravel-supported
  database (MySQL / PostgreSQL / SQLite / MariaDB / SQL Server)

> The monitor stores its data in the host application's database via a
> standard Laravel migration. **Redis is not required.**

---

## Installation

```bash
composer require romalytar/yammi-jobs-monitoring-laravel
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=jobs-monitor-config
php artisan vendor:publish --tag=jobs-monitor-migrations
php artisan migrate
```

Optionally publish the views (only if you want to customize the UI):

```bash
php artisan vendor:publish --tag=jobs-monitor-views
```

That's it. The service provider auto-registers the event listeners, so any
job dispatched after install will be tracked.

---

## Configuration

Published to `config/jobs-monitor.php`:

```php
return [
    // Enable/disable the whole package without uninstalling.
    'enabled' => env('JOBS_MONITOR_ENABLED', true),

    // Blade dashboard.
    'ui' => [
        'enabled'    => env('JOBS_MONITOR_UI_ENABLED', true),
        'path'       => env('JOBS_MONITOR_UI_PATH', 'jobs-monitor'),
        'middleware'  => ['web'],
    ],

    // JSON API — for SPAs, mobile apps, external dashboards.
    'api' => [
        'enabled'    => env('JOBS_MONITOR_API_ENABLED', false),
        'path'       => env('JOBS_MONITOR_API_PATH', 'api/jobs-monitor'),
        'middleware'  => ['api'],
    ],

    // Which queue connections to monitor. null = all.
    'connections' => null,

    // Retention — automatically prune records older than N days.
    'retention_days' => 14,

    // Notifications on failure (coming soon).
    'notifications' => [
        'enabled' => true,
        'channels' => ['mail'], // mail | slack | telegram
        'to' => env('JOBS_MONITOR_NOTIFY_TO'),
        'throttle_per_hour' => 5,
    ],
];
```

---

## Usage

### Blade Dashboard

Visit `/jobs-monitor` (or whatever you set `ui.path` to). The dashboard
shows:

- **Summary cards** — total jobs, failures in the last 24 hours, success rate
- **Recent failures** — table with job name, queue, duration, exception
- **Recent jobs** — table with status badges, connection, queue, duration

Customise the middleware to protect access:

```php
'ui' => [
    'middleware' => ['web', 'auth', 'can:viewJobsMonitor'],
],
```

Publish the views if you want to change the look:

```bash
php artisan vendor:publish --tag=jobs-monitor-views
```

### JSON API

Enable the API for external dashboards (SPA, mobile, separate frontend):

```php
// config/jobs-monitor.php
'api' => [
    'enabled' => true,
    'path'    => 'api/jobs-monitor',
    'middleware' => ['api', 'auth:sanctum'],
],
```

| Endpoint                        | Method | Description                   |
|--------------------------------|--------|-------------------------------|
| `GET /api/jobs-monitor/jobs`    | GET    | Recent jobs (newest first)    |
| `GET /api/jobs-monitor/failures`| GET    | Failed jobs in the last 24h   |
| `GET /api/jobs-monitor/stats`   | GET    | Aggregate stats per job class |

#### Query parameters

**`/jobs`**
- `limit` — max records to return (default: 50, max: 200)

**`/failures`**
- `hours` — look-back window in hours (default: 24, max: 168)

**`/stats`**
- `job_class` — **(required)** fully-qualified class name

#### Response format

```json
{
    "data": [
        {
            "uuid": "550e8400-e29b-41d4-a716-446655440000",
            "attempt": 1,
            "job_class": "App\\Jobs\\SendInvoice",
            "connection": "redis",
            "queue": "default",
            "status": "processed",
            "started_at": "2026-01-01T00:00:00+00:00",
            "finished_at": "2026-01-01T00:00:02+00:00",
            "duration_ms": 2000,
            "exception": null
        }
    ]
}
```

### Programmatic access (Facade)

```php
use Yammi\JobsMonitor\Infrastructure\Facade\JobsMonitor;

JobsMonitor::recentJobs(50);
JobsMonitor::recentFailures(24);
JobsMonitor::stats(App\Jobs\SendInvoice::class);
JobsMonitor::queueSize('default'); // null when driver doesn't support it
```

### Pruning

A scheduled command keeps the table small (coming soon):

```php
// app/Console/Kernel.php
$schedule->command('jobs-monitor:prune')->daily();
```

---

## Architecture

```
┌────────────────────────────────────────────────────────────────┐
│                Host Laravel application                        │
│                                                                │
│   dispatch(Job) ──► Queue (redis/db/sqs/sync) ──► Worker       │
│                                  │                             │
│                                  ▼                             │
│        Illuminate\Queue\Events\{JobProcessing,Processed,Failed}│
│                                  │                             │
│                                  ▼                             │
│   ┌──────────────────────────────────────────────────────┐     │
│   │   yammi-jobs-monitoring-laravel                       │    │
│   │                                                       │    │
│   │   EventListener  ─►  JobRecordRepository  ─► DB table │    │
│   │                          │                            │    │
│   │                          ├─► NotificationDispatcher   │    │
│   │                          └─► QueueMetricsDriver       │    │
│   │                                (redis|db|sqs|null)    │    │
│   │                                                       │    │
│   │   Routes  ─►  Blade UI                                │    │
│   └──────────────────────────────────────────────────────┘     │
└────────────────────────────────────────────────────────────────┘
```

Key types:

```php
// Application/Contract/QueueMetricsDriver.php
interface QueueMetricsDriver
{
    public function getQueueSize(string $queue): ?int;
    public function getDelayedSize(string $queue): ?int;
    public function getReservedSize(string $queue): ?int;
}
```

### Database schema (draft)

Single table `jobs_monitor`:

| column        | type            | notes                                  |
|---------------|-----------------|----------------------------------------|
| id            | bigint pk       |                                        |
| uuid          | char(36) unique | Laravel job uuid                       |
| job_class     | string          | fully-qualified class name             |
| connection    | string          |                                        |
| queue         | string          |                                        |
| status        | enum            | `processing` / `processed` / `failed`  |
| attempt       | unsigned int    | **retry count**                        |
| started_at    | timestamp       |                                        |
| finished_at   | timestamp null  |                                        |
| duration_ms   | unsigned int    |                                        |
| exception     | text null       | class + message                        |
| trace         | longtext null   |                                        |
| payload       | json null       | only when driver allows                |
| created_at    | timestamp       |                                        |

Indexes on `(status, created_at)`, `(job_class, created_at)`,
`(queue, created_at)`.

---

## Roadmap

### MVP (the goal of the first releases)

- [x] `composer.json` + service provider scaffold
- [x] Migration for `jobs_monitor` table
- [x] Listeners for `JobProcessing` / `JobProcessed` / `JobFailed`
- [x] Retry / attempt tracking
- [x] `QueueMetricsDriver` interface + Null implementation
- [x] `JobsMonitor` facade with the basic query API
- [x] Minimal Blade UI dashboard + JSON API
- [ ] `jobs-monitor:prune` Artisan command + scheduling docs
- [ ] Notification channel pipeline (mail to start, slack/telegram next)
- [ ] Redis / Database / Sqs metrics driver implementations
- [ ] Tests against `redis`, `database` and `sync` drivers

### Later

- [ ] Per-class aggregate dashboard (success rate, p50/p95 duration)
- [ ] Webhook notification channel
- [ ] Telegram notification channel
- [ ] Optional Prometheus exporter
- [ ] Per-queue throughput chart
- [ ] CLI `jobs-monitor:status` for headless servers
- [ ] Multi-tenant support

---

## Contributing

Branching model:

- `main` — production / released code. **Protected.**
- `dev`  — integration branch. **Protected, PR-only.**
- Feature work is done on branches off `dev`:
  - `feature-<n>-<short-name>` — new features
  - `bugfix-<n>-<short-name>`  — bug fixes
  - `hotfix-<n>-<short-name>`  — urgent fixes against `main`
  - `chore-<n>-<short-name>`   — tooling, CI, deps

Open PRs against `dev`. Releases are merged from `dev` into `main`.

Commit messages: short, imperative, English only.

---

## License

MIT — see [LICENSE](LICENSE) once published.
