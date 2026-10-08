<?php
return ['default' => env('MAIL_MAILER', 'log'), 'mailers' => [
    'log' => ['transport' => 'log', 'channel' => 'single'],
    'smtp' => ['transport' => 'smtp', 'host' => env('MAIL_HOST'), 'port' => env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD'), 'scheme' => env('MAIL_SCHEME')],
], 'from' => ['address' => env('MAIL_FROM_ADDRESS', 'noreply@example.test'), 'name' => 'GTEM']];
