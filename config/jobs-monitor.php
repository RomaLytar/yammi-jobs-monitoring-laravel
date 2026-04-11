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

];
