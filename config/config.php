<?php

return [
    'host' => env('ELASTICSEARCH_HOST', 'elasticsearch:9200'),
    'username' => env('ELASTICSEARCH_USERNAME', ''),
    'password' => env('ELASTICSEARCH_PASSWORD', ''),
    'index' => env('ELASTICSEARCH_INDEX', 'telescope_bl'),
];