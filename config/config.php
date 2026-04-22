<?php

return [
    'host'     => env('TELESCOPE_ELASTICSEARCH_HOST', 'elasticsearch:9200'),
    'username' => env('TELESCOPE_ELASTICSEARCH_USERNAME', ''),
    'password' => env('TELESCOPE_ELASTICSEARCH_PASSWORD', ''),
    'index'    => env('TELESCOPE_ELASTICSEARCH_INDEX', 'telescope'),
    'index_suffix_format' => env('TELESCOPE_ELASTICSEARCH_INDEX_SUFFIX_FORMAT'), // e.g. 'Y.m.d', 'Y.m', 'Y.m.d.H', etc.
    'ssl' => [
        'verify' => env('TELESCOPE_ELASTICSEARCH_SSL_CA', null),
        'cert'   => env('TELESCOPE_ELASTICSEARCH_SSL_CERT', null),
        'ssl_key'    => env('TELESCOPE_ELASTICSEARCH_SSL_KEY', null),
        'ssl_verification' => env('TELESCOPE_ELASTICSEARCH_SSL_VERIFICATION', true),
    ]
];
