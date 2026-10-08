<?php
return [
    'name' => env('APP_NAME', 'GTEM'), 'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false), 'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),
    'locale' => 'pt_BR', 'fallback_locale' => 'en', 'faker_locale' => 'pt_BR',
    'cipher' => 'AES-256-CBC', 'key' => env('APP_KEY'),
    'previous_keys' => [], 'maintenance' => ['driver' => 'file'],
];
