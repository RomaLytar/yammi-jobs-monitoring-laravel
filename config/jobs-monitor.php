<?php

declare(strict_types=1);

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
    | Dashboard UI
    |--------------------------------------------------------------------------
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
    | Expose the same data as the Blade dashboard via a JSON API. Useful
    | when the frontend lives in a separate project (SPA, mobile, etc).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Payload storage
    |--------------------------------------------------------------------------
    |
    | When enabled the raw job payload is stored alongside the record.
    | Sensitive keys (password, token, secret, api_key, …) are
    | automatically redacted before storage.
    |
    */

    'store_payload' => (bool) env('JOBS_MONITOR_STORE_PAYLOAD', false),

    /*
    |--------------------------------------------------------------------------
    | Failure classifier
    |--------------------------------------------------------------------------
    |
    | Override the default pattern-based failure classifier with your own.
    | Set this to a fully-qualified class name that implements
    | \Yammi\JobsMonitor\Domain\Job\Contract\FailureClassifier.
    |
    | When null the built-in PatternBasedFailureClassifier is used.
    |
    */

    'failure_classifier' => null,

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to keep records. The jobs-monitor:prune command
    | deletes everything older than this. Schedule it daily via
    | $schedule->command('jobs-monitor:prune')->daily() in your kernel.
    |
    */

    'retention_days' => (int) env('JOBS_MONITOR_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Dead Letter Queue
    |--------------------------------------------------------------------------
    |
    | max_tries — threshold used by the DLQ page. A job UUID is treated as
    | "dead" when its latest attempt is failed AND either the failure
    | category is permanent/critical OR the attempt number reached
    | this value.
    |
    | dlq.authorization — optional Gate ability name. When set, the
    | host app's Gate::define('<ability>', fn(User $u, string $action) => ...)
    | is consulted before retry/delete. $action is 'retry' or 'delete'.
    | Null means no authorization — fine for single-user local setups but
    | NOT recommended in production UIs open to multiple users.
    |
    */

    'max_tries' => (int) env('JOBS_MONITOR_MAX_TRIES', 3),

    'dlq' => [
        'authorization' => env('JOBS_MONITOR_DLQ_GATE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bulk UI
    |--------------------------------------------------------------------------
    |
    | max_ids_per_request: hard cap on one bulk retry/delete HTTP request.
    |   The JS chunker never sends more than this. Overriding it above 100
    |   is discouraged — it makes a single request long-running.
    | candidate_limit: cap on the "Select all matching" fetch. Selections
    |   larger than this are truncated; the UI tells the operator to narrow
    |   the filter.
    |
    */

    'bulk' => [
        'max_ids_per_request' => (int) env('JOBS_MONITOR_BULK_MAX_IDS', 100),
        'candidate_limit' => (int) env('JOBS_MONITOR_BULK_CANDIDATE_LIMIT', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings UI
    |--------------------------------------------------------------------------
    |
    | The /settings page lets operators flip feature toggles, edit alert
    | rules, and manage recipient lists from the UI. Changes here have a
    | wider blast radius than retrying a single DLQ entry, so a stricter
    | gate is recommended in production.
    |
    | Null means no authorization — fine for single-user local setups but
    | NOT recommended where multiple users can access the dashboard.
    |
    */

    'settings' => [
        'authorization' => env('JOBS_MONITOR_SETTINGS_GATE'),
    ],

    'api' => [
        'enabled' => (bool) env('JOBS_MONITOR_API_ENABLED', false),
        'path' => env('JOBS_MONITOR_API_PATH', 'api/jobs-monitor'),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    |
    | Proactive notifications when failure conditions cross thresholds. Off by
    | default — set JOBS_MONITOR_ALERTS_ENABLED=true to turn on.
    |
    | Requires a running queue worker: alert delivery is queued so it never
    | blocks the host job that just failed.
    |
    | Built-in rules ship pre-configured. Override any by its id here; set
    | `enabled => false` to disable, or tweak threshold/channels/etc. Add
    | your own rules under `custom_rules`.
    |
    | Previewing without real Slack/SMTP (dev):
    |   - Slack: use https://webhook.site to grab a catch-all URL
    |   - Mail:  set MAIL_MAILER=log to dump emails into storage/logs
    |
    */

    'alerts' => [
        'enabled' => (bool) env('JOBS_MONITOR_ALERTS_ENABLED', false),

        // Human-readable site label shown on every alert (e.g. "MyApp (prod)").
        // Null falls back to Laravel's APP_NAME. Override when you run several
        // environments against the same Slack channel and need to tell them
        // apart at a glance.
        'source_name' => env('JOBS_MONITOR_ALERT_SOURCE_NAME'),

        // Base URL the package uses to build deep links inside every alert.
        // Null auto-composes APP_URL + jobs-monitor.ui.path — use this env
        // only if you serve the monitor on a different host than app.url.
        'monitor_url' => env('JOBS_MONITOR_ALERT_MONITOR_URL'),

        'channels' => [
            'slack' => [
                'webhook_url' => env('JOBS_MONITOR_SLACK_WEBHOOK'),
                'signing_secret' => env('JOBS_MONITOR_SLACK_SIGNING_SECRET'),
            ],
            'mail' => [
                // Comma-separated list in the env, e.g. "ops@acme.com,oncall@acme.com"
                'to' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env('JOBS_MONITOR_ALERT_MAIL_TO', '')),
                ))),
            ],

            // PagerDuty Events API v2. Empty routing_key → channel not
            // registered (no-op). Repeat alerts with the same dedup_key
            // (= fingerprint) attach to the existing incident instead of
            // opening a new one every evaluation tick.
            'pagerduty' => [
                'routing_key' => env('JOBS_MONITOR_PAGERDUTY_ROUTING_KEY'),
            ],

            // Opsgenie Alert API v2. Region: 'us' (default) or 'eu'.
            // Empty api_key → channel not registered.
            'opsgenie' => [
                'api_key' => env('JOBS_MONITOR_OPSGENIE_API_KEY'),
                'region' => env('JOBS_MONITOR_OPSGENIE_REGION', 'us'),
            ],

            // Generic signed webhook for any other incident hub
            // (Grafana OnCall, Splunk On-Call, VictorOps, internal).
            // Body is POSTed as JSON and signed with HMAC-SHA256 using
            // `secret` (X-Jobs-Monitor-Signature header). `headers` is
            // merged into every request so hosts can pass a bearer
            // token or tenant header.
            'webhook' => [
                'url' => env('JOBS_MONITOR_WEBHOOK_URL'),
                'secret' => env('JOBS_MONITOR_WEBHOOK_SECRET'),
                'headers' => [],
                'timeout' => (int) env('JOBS_MONITOR_WEBHOOK_TIMEOUT', 5),
            ],
        ],

        'built_in' => [
            // Any job falling into the "critical" failure category.
            // Default: enabled, threshold 1, both channels.
            'critical_failure' => [
                // 'enabled' => false,
                // 'threshold' => 1,
                // 'channels' => ['slack', 'mail'],
                // 'cooldown_minutes' => 10,
            ],

            // Retry-aware: only counts failures at attempt >= 2.
            // Silences first-try noise; screams when retries pile up.
            // Default: enabled, threshold 5, slack only.
            'retry_storm' => [
                // 'enabled' => false,
                // 'threshold' => 5,
                // 'window' => '10m',
                // 'min_attempt' => 2,
                // 'channels' => ['slack'],
            ],

            // Raw failure rate — noisy on busy queues, off by default.
            // Enable after you know your baseline.
            'high_failure_rate' => [
                'enabled' => (bool) env('JOBS_MONITOR_ALERT_HIGH_FAILURE_RATE', false),
                // 'threshold' => 20,
                // 'window' => '5m',
            ],

            // DLQ size has grown past threshold. Off by default.
            'dlq_growing' => [
                'enabled' => (bool) env('JOBS_MONITOR_ALERT_DLQ_GROWING', false),
                // 'threshold' => 10,
            ],

            // First time a never-before-seen failure signature appears.
            // One alert per evaluation tick listing every new fingerprint.
            // Default: enabled, threshold 1, window 15m, slack only.
            'new_failure_group' => [
                // 'enabled' => false,
                // 'threshold' => 1,
                // 'window' => '15m',
                // 'cooldown_minutes' => 15,
                // 'channels' => ['slack'],
            ],

            // Per-group burst: a known fingerprint accumulates failures fast.
            // One alert per bursting group with its own per-fingerprint
            // throttle so a chronically noisy group does not silence quieter
            // ones. Default: enabled, threshold 5 in 5m, cooldown 15m.
            'failure_group_burst' => [
                // 'enabled' => false,
                // 'threshold' => 5,
                // 'window' => '5m',
                // 'cooldown_minutes' => 15,
                // 'channels' => ['slack'],
            ],
        ],

        // Free-form rules added by the host. Same shape as a built-in:
        //   [
        //       'trigger' => 'job_class_failure_rate',
        //       'value' => 'App\\Jobs\\SendInvoice',
        //       'window' => '30m',
        //       'threshold' => 3,
        //       'channels' => ['slack'],
        //       'cooldown_minutes' => 30,
        //   ]
        'custom_rules' => [],

        'schedule' => [
            // When true the SP registers the minute-by-minute evaluation job
            // automatically. Disable if you want to trigger it yourself.
            'enabled' => (bool) env('JOBS_MONITOR_ALERTS_SCHEDULE_ENABLED', true),
            'cron' => env('JOBS_MONITOR_ALERTS_CRON', '* * * * *'),
            // Queue name for the dispatched evaluation job.
            'queue' => env('JOBS_MONITOR_ALERTS_QUEUE', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled task monitoring
    |--------------------------------------------------------------------------
    |
    | Observes Laravel's scheduler (`$schedule->command(...)`) and records
    | every run. The watchdog flags runs that stayed in Running past
    | `tolerance_minutes` as Late — typical cause is a worker/host crash
    | that prevented the Finished event.
    |
    */

    'scheduler' => [
        'enabled' => (bool) env('JOBS_MONITOR_SCHEDULER_ENABLED', true),

        'watchdog' => [
            'enabled' => (bool) env('JOBS_MONITOR_SCHEDULER_WATCHDOG_ENABLED', true),
            'tolerance_minutes' => (int) env('JOBS_MONITOR_SCHEDULER_TOLERANCE_MINUTES', 30),
            'cron' => env('JOBS_MONITOR_SCHEDULER_WATCHDOG_CRON', '*/5 * * * *'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job duration anomaly detection
    |--------------------------------------------------------------------------
    |
    | Keeps a rolling baseline (p50/p95) per job class and flags outliers.
    | Catches the "job reported success but ran 100x faster than normal,
    | likely no-op'd" case. `min_samples` protects young baselines from
    | firing on insufficient data.
    |
    */

    'duration_anomaly' => [
        'enabled' => (bool) env('JOBS_MONITOR_DURATION_ANOMALY_ENABLED', true),
        'min_samples' => (int) env('JOBS_MONITOR_DURATION_ANOMALY_MIN_SAMPLES', 30),
        // Flag runs that are faster than (p50 * short_factor).
        'short_factor' => (float) env('JOBS_MONITOR_DURATION_ANOMALY_SHORT_FACTOR', 0.1),
        // Flag runs that are slower than (p95 * long_factor).
        'long_factor' => (float) env('JOBS_MONITOR_DURATION_ANOMALY_LONG_FACTOR', 3.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Job outcome reports
    |--------------------------------------------------------------------------
    |
    | When a job implements \Yammi\JobsMonitor\Domain\Job\Contract\ReportsOutcome
    | and returns an OutcomeReport from outcome(), the monitor stores it and
    | can alert on suspicious outcomes (e.g. zero processed when the job
    | historically processes > 0).
    |
    */

    'outcome' => [
        'enabled' => (bool) env('JOBS_MONITOR_OUTCOME_ENABLED', true),
    ],

];
