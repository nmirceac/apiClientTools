<?php

return [
    'endpoint'=> [
        'baseUrl'=>env('API_CLIENT_BASE_URL', null),
        'secret'=>env('API_CLIENT_SECRET', null),
        'ignoreSslHost'=>env('API_CLIENT_IGNORE_SSL_HOST', false),
        'ignoreSslErrors'=>env('API_CLIENT_IGNORE_SSL_ERRORS', false),
    ],

    'sendAuth'=>env('API_CLIENT_SEND_AUTH', true),
    'sendLocale'=>env('API_CLIENT_SEND_LOCALE', true),
    'authCaching'=>env('API_CLIENT_AUTH_CACHE_TIMEOUT', 0),

    'caching'=>env('API_CLIENT_CACHE_TIMEOUT', 0),

    'debug'=>env('API_CLIENT_DEBUG', false),
    'ray'=>env('API_CLIENT_RAY', false),
    'ray_thumbnails'=>env('API_CLIENT_RAY_THUMBNAILS', false),
    'ray_response_trim'=>env('API_CLIENT_RAY_RESPONSE_TRIM', 0),

    'impersonator_id_session_variable'=>env('API_CLIENT_IMPERSONATOR_ID_SESSION_VARIABLE', 'impersonator_id'),

    'baseNamespace'=>env('API_CLIENT_BASE_NAMESPACE', 'Api'),

    'colorTools'=> [
        'autoDetect' => env('API_CLIENT_COLOR_TOOLS_AUTODETECT', true),
        'publicPattern' => env('API_CLIENT_COLOR_TOOLS_PUBLIC_PATTERN', 'images/%hash%'),
    ],
];
