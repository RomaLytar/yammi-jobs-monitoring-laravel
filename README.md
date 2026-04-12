# yammi-jobs-monitoring-laravel

Lightweight queue monitoring for Laravel. Works with **any** queue driver (Redis, Database, SQS, Sync). Tracks job lifecycle, retries, failures, and provides a Blade dashboard + JSON API.

## Requirements

- PHP `^8.1`
- Laravel `^9.0 || ^10.0 || ^11.0`

## Installation

```bash
composer require romalytar/yammi-jobs-monitoring-laravel

php artisan vendor:publish --tag=jobs-monitor-config
php artisan vendor:publish --tag=jobs-monitor-migrations
php artisan migrate
```

The service provider auto-registers. All dispatched jobs are tracked automatically.

## Configuration

Published to `config/jobs-monitor.php`:

```php
return [
    // Master switch — disable without uninstalling.
    'enabled' => env('JOBS_MONITOR_ENABLED', true),

    // Store job payload (disabled by default for privacy).
    // Sensitive keys (password, token, secret, api_key, etc.)
    // are automatically replaced with ********.
    'store_payload' => env('JOBS_MONITOR_STORE_PAYLOAD', false),

    // Blade dashboard.
    'ui' => [
        'enabled'    => env('JOBS_MONITOR_UI_ENABLED', true),
        'path'       => env('JOBS_MONITOR_UI_PATH', 'jobs-monitor'),
        'middleware'  => ['web'],
    ],

    // JSON API for external frontends (SPA, mobile, etc.).
    'api' => [
        'enabled'    => env('JOBS_MONITOR_API_ENABLED', false),
        'path'       => env('JOBS_MONITOR_API_PATH', 'api/jobs-monitor'),
        'middleware'  => ['api'],
    ],
];
```

## Blade Dashboard

Visit `/jobs-monitor` (or your custom `ui.path`).

**What you see:**
- **5 status cards** — Total, Processed, Failed, Processing, Success Rate
- **Failed Jobs table** — separate block with its own pagination (10/page) and sorting
- **All Jobs table** — all records with pagination (50/page) and sorting
- **Accordion details** — click any row to expand: UUID, full class, payload, exception
- **Period filter** — `1m`, `5m`, `30m`, `1h`, `6h`, `24h`, `7d`, `30d`, `all`
- **Search** — filter by job class name (substring match)
- **Sorting** — click column headers: Job, Started At, Duration, Status (All Jobs only)

Protect access with middleware:

```php
'ui' => [
    'middleware' => ['web', 'auth', 'can:viewJobsMonitor'],
],
```

Customize the views:

```bash
php artisan vendor:publish --tag=jobs-monitor-views
```

## JSON API

Enable in config:

```php
'api' => [
    'enabled' => true,
    'middleware' => ['api', 'auth:sanctum'],
],
```

### Endpoints

| Endpoint | Description |
|---|---|
| `GET /api/jobs-monitor/jobs` | All jobs (paginated) |
| `GET /api/jobs-monitor/failures` | Failed jobs only (paginated) |
| `GET /api/jobs-monitor/stats?job_class=...` | Aggregate stats per job class |

### Query parameters

All endpoints support:

| Parameter | Description | Default |
|---|---|---|
| `period` | Time window: `1m`, `5m`, `30m`, `1h`, `6h`, `24h`, `7d`, `30d`, `all` | `24h` |
| `search` | Filter by job class (substring) | — |
| `sort` | Sort by: `started_at`, `status`, `duration_ms`, `job_class` | `started_at` |
| `dir` | Sort direction: `asc`, `desc` | `desc` |
| `page` | Page number | `1` |
| `per_page` | Records per page (max 200) | `50` (jobs) / `10` (failures) |

**`/stats` also requires:**

| Parameter | Description |
|---|---|
| `job_class` | **(required)** Fully-qualified class name |

### Response format

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
            "started_at": "2026-04-12T12:00:00+00:00",
            "finished_at": "2026-04-12T12:00:02+00:00",
            "duration_ms": 2000,
            "exception": null,
            "payload": { "..." }
        }
    ],
    "meta": {
        "total": 400,
        "page": 1,
        "per_page": 50,
        "last_page": 8
    }
}
```

## Payload & Security

When `store_payload` is enabled, the raw job payload is saved. Before displaying (in the dashboard and API), the package:

1. **Deserializes** PHP serialized command data into readable key-value pairs
2. **Redacts** any key containing: `password`, `token`, `secret`, `api_key`, `authorization`, `credit_card`, `cvv`, `ssn` — values are replaced with `********`
3. Redaction works **recursively** at any nesting depth and on any payload structure

When `store_payload` is `false` (default), no payload data is stored.

## Facade

```php
use Yammi\JobsMonitor\Infrastructure\Facade\JobsMonitor;

JobsMonitor::recentJobs(50);
JobsMonitor::recentFailures(24);
JobsMonitor::stats(App\Jobs\SendInvoice::class);
JobsMonitor::queueSize('default'); // null if driver doesn't support it
```

## License

MIT
