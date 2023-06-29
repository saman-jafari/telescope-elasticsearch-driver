<?php

return [
    'host' => env('TELESCOPE_ELASTICSEARCH_HOST', 'elasticsearch:9200'),
    'username' => env('TELESCOPE_ELASTICSEARCH_USERNAME', ''),
    'password' => env('TELESCOPE_ELASTICSEARCH_PASSWORD', ''),
    'index' => env('TELESCOPE_ELASTICSEARCH_INDEX', 'telescope'),
];