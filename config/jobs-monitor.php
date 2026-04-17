<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| jobs-monitor — boot-time & secrets config
|--------------------------------------------------------------------------
|
| This file contains settings that MUST be explicitly configured:
| credentials, connection names, route paths, and middleware.
|
| Operational defaults (retention, thresholds, feature toggles) live in
| jobs-monitor-defaults.php and are pre-set — you only need to touch them
| if you want to deviate from the defaults or prefer config over the UI.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Disable the package without uninstalling it. When false the service
    | provider still boots but registers no listeners and exposes no UI.
    |
    */

    'enabled' => (bool) env('JOBS_MONITOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Dedicated database connection (optional)
    |--------------------------------------------------------------------------
    |
    | By default all monitoring data is stored in your application's default
    | database. If you want to isolate it in a separate database, follow the
    | three steps below. Leave this null to skip and use the default DB.
    |
    | STEP 1 — add a connection block to config/database.php.
    |          Pick any key name you like (e.g. "monitor", "jobs_monitor").
    |          That key is what you will put in JOBS_MONITOR_DB_CONNECTION.
    |
    |   'connections' => [
    |       // ... your existing connections ...
    |
    |       'monitor' => [                    // ← the key name, you choose it
    |           'driver'    => 'mysql',
    |           'host'      => env('JOBS_MONITOR_DB_HOST', '127.0.0.1'),
    |           'port'      => env('JOBS_MONITOR_DB_PORT', '3306'),
    |           'database'  => env('JOBS_MONITOR_DB_DATABASE', 'monitor_db'), // ← actual DB name
    |           'username'  => env('JOBS_MONITOR_DB_USERNAME', 'root'),
    |           'password'  => env('JOBS_MONITOR_DB_PASSWORD', ''),
    |           'charset'   => 'utf8mb4',
    |           'collation' => 'utf8mb4_unicode_ci',
    |           'prefix'    => '',
    |           'strict'    => true,
    |           'engine'    => null,
    |       ],
    |   ],
    |
    | STEP 2 — add the matching env variables to .env:
    |
    |   JOBS_MONITOR_DB_CONNECTION=monitor   ← must match the key from Step 1
    |   JOBS_MONITOR_DB_HOST=127.0.0.1
    |   JOBS_MONITOR_DB_DATABASE=monitor_db  ← the actual database name in MySQL/Postgres
    |   JOBS_MONITOR_DB_USERNAME=root
    |   JOBS_MONITOR_DB_PASSWORD=secret
    |
    | STEP 3 — open Settings → Database Connection in the UI and click
    |          "Setup Monitor DB & Transfer Data" to create the database,
    |          run migrations, and move existing rows automatically.
    |          Or run the command manually:
    |
    |   php artisan jobs-monitor:transfer-data
    |
    | Note: if you ever want to go back to the default database, remove
    | JOBS_MONITOR_DB_CONNECTION from .env and run the command in reverse:
    |
    |   php artisan jobs-monitor:transfer-data --from=monitor --to=mysql --delete-source
    |
    */

    'database' => [
        'connection' => env('JOBS_MONITOR_DB_CONNECTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard UI
    |--------------------------------------------------------------------------
    |
    | middleware: add 'auth' (or your guard) to protect the dashboard.
    | Default is ['web'] — unauthenticated, suitable for local dev only.
    |
    */

    'ui' => [
        'enabled' => (bool) env('JOBS_MONITOR_UI_ENABLED', true),
        'path' => env('JOBS_MONITOR_UI_PATH', 'jobs-monitor'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON API
    |--------------------------------------------------------------------------
    |
    | Expose the same data via a JSON API (off by default).
    | Set middleware to 'auth:sanctum' or your token guard.
    |
    */

    'api' => [
        'enabled' => (bool) env('JOBS_MONITOR_API_ENABLED', false),
        'path' => env('JOBS_MONITOR_API_PATH', 'api/jobs-monitor'),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization gates
    |--------------------------------------------------------------------------
    |
    | Optional Gate ability names for destructive UI actions.
    | Null = no authorization check (fine for local dev, NOT for production).
    |
    | Define in AuthServiceProvider:
    |   Gate::define('jobs-monitor-dlq', fn (User $u, string $action) => $u->isAdmin());
    |
    */

    'dlq' => [
        'authorization' => env('JOBS_MONITOR_DLQ_GATE'),
    ],

    'settings' => [
        'authorization' => env('JOBS_MONITOR_SETTINGS_GATE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom failure classifier
    |--------------------------------------------------------------------------
    |
    | Fully-qualified class name implementing FailureClassifier, or null
    | to use the built-in PatternBasedFailureClassifier.
    |
    */

    'failure_classifier' => null,

    /*
    |--------------------------------------------------------------------------
    | Alert channels — credentials
    |--------------------------------------------------------------------------
    |
    | A channel is automatically skipped when its key credential is null.
    | Never put real credentials in this file — use .env.
    |
    */

    'alerts' => [

        'channels' => [

            'slack' => [
                'webhook_url' => env('JOBS_MONITOR_SLACK_WEBHOOK'),
                'signing_secret' => env('JOBS_MONITOR_SLACK_SIGNING_SECRET'),
            ],

            'mail' => [
                // Comma-separated list: "ops@acme.com,oncall@acme.com"
                'to' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env('JOBS_MONITOR_ALERT_MAIL_TO', '')),
                ))),
            ],

            // PagerDuty Events API v2. Empty routing_key → channel not registered.
            'pagerduty' => [
                'routing_key' => env('JOBS_MONITOR_PAGERDUTY_ROUTING_KEY'),
            ],

            // Opsgenie Alert API v2. region: 'us' (default) or 'eu'.
            'opsgenie' => [
                'api_key' => env('JOBS_MONITOR_OPSGENIE_API_KEY'),
                'region' => env('JOBS_MONITOR_OPSGENIE_REGION', 'us'),
            ],

            // Generic signed webhook (Grafana OnCall, Splunk, internal tooling, …).
            'webhook' => [
                'url' => env('JOBS_MONITOR_WEBHOOK_URL'),
                'secret' => env('JOBS_MONITOR_WEBHOOK_SECRET'),
                'headers' => [],
                'timeout' => (int) env('JOBS_MONITOR_WEBHOOK_TIMEOUT', 5),
            ],

        ],

        // Free-form rules added by the host application in code.
        // These are NOT editable from the UI — use the Settings page for that.
        // Shape: ['trigger'=>..., 'threshold'=>..., 'window'=>..., 'channels'=>[...], ...]
        'custom_rules' => [],

        'schedule' => [
            // Cron expression for the alert evaluation job. Change only if you
            // need a different cadence (e.g. every 5 min to reduce DB load).
            'cron' => env('JOBS_MONITOR_ALERTS_CRON', '* * * * *'),
            // Queue for the dispatched evaluation job. Null = default queue.
            'queue' => env('JOBS_MONITOR_ALERTS_QUEUE', null),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule crons — boot-time only
    |--------------------------------------------------------------------------
    |
    | These cron expressions are used when registering commands in the
    | Laravel scheduler. They can only be changed here, not via the UI.
    |
    */

    'scheduler' => [
        'watchdog' => [
            'cron' => env('JOBS_MONITOR_SCHEDULER_WATCHDOG_CRON', '*/5 * * * *'),
        ],
    ],

    'workers' => [

        // Map of "connection:queue" → minimum alive worker count.
        // When fewer alive workers are observed the WORKER_UNDERPROVISIONED
        // alert fires. Empty map = underprovisioned check disabled.
        'expected' => [],

        'schedule' => [
            'cron' => env('JOBS_MONITOR_WORKERS_CRON', '* * * * *'),
        ],

    ],

];
