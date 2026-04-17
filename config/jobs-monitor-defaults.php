<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| jobs-monitor — operational defaults
|--------------------------------------------------------------------------
|
| These settings have sensible defaults and can be adjusted at runtime
| through the dashboard Settings page (stored in jobs_monitor_settings).
|
| You do NOT need to publish or edit this file. To override a value via
| config instead of the UI, add the key to your published jobs-monitor.php.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Payload storage
    |--------------------------------------------------------------------------
    */

    'store_payload' => (bool) env('JOBS_MONITOR_STORE_PAYLOAD', true),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */

    'retention_days' => (int) env('JOBS_MONITOR_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Dead Letter Queue threshold
    |--------------------------------------------------------------------------
    */

    'max_tries' => (int) env('JOBS_MONITOR_MAX_TRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Bulk UI limits
    |--------------------------------------------------------------------------
    */

    'bulk' => [
        'max_ids_per_request' => (int) env('JOBS_MONITOR_BULK_MAX_IDS', 100),
        'candidate_limit' => (int) env('JOBS_MONITOR_BULK_CANDIDATE_LIMIT', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts — operational settings
    |--------------------------------------------------------------------------
    */

    'alerts' => [

        'enabled' => (bool) env('JOBS_MONITOR_ALERTS_ENABLED', false),

        // Human-readable site label on every alert. Null → APP_NAME.
        'source_name' => env('JOBS_MONITOR_ALERT_SOURCE_NAME'),

        // Base URL for deep links inside alerts. Null → APP_URL + ui.path.
        'monitor_url' => env('JOBS_MONITOR_ALERT_MONITOR_URL'),

        'schedule' => [
            'enabled' => (bool) env('JOBS_MONITOR_ALERTS_SCHEDULE_ENABLED', true),
        ],

        'built_in' => [
            // Any job classified as "critical" failure.
            'critical_failure' => [
                // 'enabled'          => true,
                // 'threshold'        => 1,
                // 'channels'         => ['slack', 'mail'],
                // 'cooldown_minutes' => 10,
            ],

            // Failures at attempt >= 2 — silences first-try noise.
            'retry_storm' => [
                // 'enabled'     => true,
                // 'threshold'   => 5,
                // 'window'      => '10m',
                // 'min_attempt' => 2,
                // 'channels'    => ['slack'],
            ],

            // Raw failure rate — off by default; enable after you know your baseline.
            'high_failure_rate' => [
                'enabled' => (bool) env('JOBS_MONITOR_ALERT_HIGH_FAILURE_RATE', false),
                // 'threshold' => 20,
                // 'window'    => '5m',
            ],

            // DLQ size grew past threshold — off by default.
            'dlq_growing' => [
                'enabled' => (bool) env('JOBS_MONITOR_ALERT_DLQ_GROWING', false),
                // 'threshold' => 10,
            ],

            // First occurrence of a never-before-seen failure signature.
            'new_failure_group' => [
                // 'enabled'          => true,
                // 'threshold'        => 1,
                // 'window'           => '15m',
                // 'cooldown_minutes' => 15,
                // 'channels'         => ['slack'],
            ],

            // A known fingerprint accumulates failures faster than usual.
            'failure_group_burst' => [
                // 'enabled'          => true,
                // 'threshold'        => 5,
                // 'window'           => '5m',
                // 'cooldown_minutes' => 15,
                // 'channels'         => ['slack'],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled task monitoring
    |--------------------------------------------------------------------------
    */

    'scheduler' => [
        'enabled' => (bool) env('JOBS_MONITOR_SCHEDULER_ENABLED', true),

        'watchdog' => [
            'enabled' => (bool) env('JOBS_MONITOR_SCHEDULER_WATCHDOG_ENABLED', true),
            'tolerance_minutes' => (int) env('JOBS_MONITOR_SCHEDULER_TOLERANCE_MINUTES', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job duration anomaly detection
    |--------------------------------------------------------------------------
    */

    'duration_anomaly' => [
        'enabled' => (bool) env('JOBS_MONITOR_DURATION_ANOMALY_ENABLED', true),
        'min_samples' => (int) env('JOBS_MONITOR_DURATION_ANOMALY_MIN_SAMPLES', 30),
        'short_factor' => (float) env('JOBS_MONITOR_DURATION_ANOMALY_SHORT_FACTOR', 0.1),
        'long_factor' => (float) env('JOBS_MONITOR_DURATION_ANOMALY_LONG_FACTOR', 3.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Job outcome reports
    |--------------------------------------------------------------------------
    */

    'outcome' => [
        'enabled' => (bool) env('JOBS_MONITOR_OUTCOME_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker heartbeat monitoring — operational settings
    |--------------------------------------------------------------------------
    */

    'workers' => [
        'enabled' => (bool) env('JOBS_MONITOR_WORKERS_ENABLED', true),
        'heartbeat_interval_seconds' => (int) env('JOBS_MONITOR_WORKERS_HEARTBEAT_INTERVAL', 30),
        'silent_after_seconds' => (int) env('JOBS_MONITOR_WORKERS_SILENT_AFTER', 120),
        'retention_days' => (int) env('JOBS_MONITOR_WORKERS_RETENTION_DAYS', 7),
        'channels' => ['slack', 'mail', 'pagerduty', 'opsgenie', 'webhook'],

        'schedule' => [
            'enabled' => (bool) env('JOBS_MONITOR_WORKERS_SCHEDULE_ENABLED', true),
        ],
    ],

];
