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

];
