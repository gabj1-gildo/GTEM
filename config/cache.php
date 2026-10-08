<?php
return [
    'default' => env('CACHE_STORE', 'file'), 'prefix' => 'gtem_',
    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],
        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data')],
    ],
];
