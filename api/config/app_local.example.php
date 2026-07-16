<?php
declare(strict_types=1);

use function Cake\Core\env;

return [
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),
    'Security' => [
        'salt' => env('SECURITY_SALT', 'change-this-development-salt'),
    ],
    'App' => [
        'fullBaseUrl' => env('APP_FULL_BASE_URL', 'http://localhost:8080'),
        'frontendOrigin' => env('FRONTEND_ORIGIN', 'http://localhost:5173'),
    ],
    'Datasources' => [
        'default' => [
            'url' => env('DATABASE_URL'),
        ],
        'test' => [
            'url' => env('DATABASE_TEST_URL', 'sqlite://127.0.0.1/tmp/tests.sqlite'),
        ],
    ],
];
