<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sentry DSN
    |--------------------------------------------------------------------------
    | Set SENTRY_LARAVEL_DSN in your environment. Leave empty to disable Sentry.
    */

    'dsn' => env('SENTRY_LARAVEL_DSN'),

    /*
    |--------------------------------------------------------------------------
    | Trace Sample Rate
    |--------------------------------------------------------------------------
    | Fraction of transactions to send to Sentry (0.0 – 1.0).
    | 0.05 = 5% — a reasonable default for production to control costs.
    */

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.05),

    /*
    |--------------------------------------------------------------------------
    | Profile Sample Rate
    |--------------------------------------------------------------------------
    */

    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),

    /*
    |--------------------------------------------------------------------------
    | Breadcrumbs
    |--------------------------------------------------------------------------
    */

    'breadcrumbs' => [
        'logs'                    => true,
        'cache'                   => false,
        'livewire'                => false,
        'sql_bindings'            => env('APP_DEBUG', false),
        'queue_info'              => true,
        'command_info'            => true,
        'http_client_requests'    => env('APP_DEBUG', false),
        'notifications'           => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracing
    |--------------------------------------------------------------------------
    */

    'tracing' => [
        'queue_job_transactions'         => env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs'                     => true,
        'sql_queries'                    => env('APP_DEBUG', false),
        'sql_origin'                     => false,
        'http_client_requests'           => false,
        'redis_commands'                 => false,
        'missing_routes'                 => false,
        'views'                          => false,
        'livewire'                       => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    */

    'environment' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Release — set via CI to the git SHA for source-map matching
    |--------------------------------------------------------------------------
    */

    'release' => env('SENTRY_RELEASE', null),

    /*
    |--------------------------------------------------------------------------
    | Send default PII (user IP, cookies, etc.)
    |--------------------------------------------------------------------------
    */

    'send_default_pii' => false,
];
