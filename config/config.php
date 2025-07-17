<?php

return [
    'host'     => env('TELESCOPE_ELASTICSEARCH_HOST', 'elasticsearch:9200'),
    'username' => env('TELESCOPE_ELASTICSEARCH_USERNAME', ''),
    'password' => env('TELESCOPE_ELASTICSEARCH_PASSWORD', ''),
    'index'    => env('TELESCOPE_ELASTICSEARCH_INDEX', 'telescope'),
    'index_suffix_format' => env('TELESCOPE_ELASTICSEARCH_INDEX_SUFFIX_FORMAT'), // e.g. 'Y.m.d', 'Y.m', 'Y.m.d.H', etc.
];