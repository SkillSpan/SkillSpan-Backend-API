<?php

return [
    'name' => env('AUTH_COOKIE_NAME', 'skillspan_auth'),
    'minutes' => (int) env(
        'AUTH_COOKIE_MINUTES',
        env('SANCTUM_TOKEN_EXPIRATION_MINUTES', 20160)
    ),
    'path' => '/',
    'domain' => env('AUTH_COOKIE_DOMAIN'),
    'secure' => env('AUTH_COOKIE_SECURE', env('APP_ENV') === 'production'),
    'same_site' => env('AUTH_COOKIE_SAME_SITE', env('APP_ENV') === 'production' ? 'none' : 'lax'),
    // Transitional escape hatch for trusted non-browser clients. Keep false in
    // deployed environments so access tokens never enter browser JavaScript.
    'expose_token' => (bool) env('AUTH_COOKIE_EXPOSE_TOKEN', false),
];
