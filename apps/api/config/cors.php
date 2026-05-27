<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS Paths
    |--------------------------------------------------------------------------
    |
    | Two distinct surfaces:
    |   - /api/v1/public/* — widget-facing, accepts any origin (no auth cookie)
    |   - /api/v1/*        — dashboard API, credentials required, FRONTEND_URL only
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],

    'max_age' => 3600,

    // Credentials are required for cookie-based Sanctum auth (dashboard).
    // Public widget endpoints override this via their own middleware group.
    'supports_credentials' => true,

];
