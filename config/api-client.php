<?php

return [
    'router'=> [
        'includeRoutes'=>true,
        'prefix'=>'api',
        'namedPrefix'=>'api-tools',
        'schemaEndpoint'=>'schema',
        'middleware' => [
            'api'
        ],
    ],

    'api' => [
        'currentVersion'=>'master',
        'requestHeaders'=>[
            'x-api-key'=>'some_secret',
        ]
    ],
];

