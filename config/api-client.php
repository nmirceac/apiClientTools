<?php

return [
    'endpoint'=> [
        'baseUrl'=>env('API_CLIENT_BASE_URL', null),
        'secret'=>env('API_CLIENT_SECRET', null),
    ],

    'baseNamespace'=>env('API_CLIENT_BASE_NAMESPACE', 'Api'),

];

