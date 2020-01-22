<?php

return [
    'endpoint'=> [
        'baseUrl'=>env('API_CLIENT_BASE_URL', null),
        'secret'=>env('API_CLIENT_SECRET', null),
    ],

    'debug'=>env('API_CLIENT_DEBUG', false),

    'baseNamespace'=>env('API_CLIENT_BASE_NAMESPACE', 'Api'),

    'colorTools'=> [
        'autoDetect' => env('API_CLIENT_COLOR_TOOLS_AUTODETECT', true),
        'publicPattern' => env('API_CLIENT_COLOR_TOOLS_PUBLIC_PATTERN', 'images/%hash%'),
    ],
];
