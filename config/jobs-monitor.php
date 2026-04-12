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

];
