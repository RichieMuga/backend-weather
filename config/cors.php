<?php

return [
    'paths' => ['api/*', 'weather', 'cities/search'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // Restrict to specific origins in production
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
