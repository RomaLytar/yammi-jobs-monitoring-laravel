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

    'api' => [
        'enabled' => (bool) env('JOBS_MONITOR_API_ENABLED', false),
        'path' => env('JOBS_MONITOR_API_PATH', 'api/jobs-monitor'),
        'middleware' => ['api'],
    ],

];
