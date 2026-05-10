<?php

$defaultFrontend = 'http://localhost:5173';
$frontend = env('FRONTEND_URL', $defaultFrontend);
$extra = env('CORS_ALLOWED_ORIGINS');

if ($extra !== null && $extra !== '') {
    $origins = array_values(array_filter(array_map('trim', explode(',', $extra))));
} else {
    $origins = [$frontend];
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', true),

];
