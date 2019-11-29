<?php

return [
    'store'     => [
        'url'           => env('STORE_URL', ''),
        'api_version'   => env('STORE_API_VERSION', 'wc/v3'),
        'key'           => env('STORE_CK', null),
        'secret'        => env('STORE_CS', null),
    ],
];
