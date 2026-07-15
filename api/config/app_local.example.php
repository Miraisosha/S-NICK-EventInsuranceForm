<?php
declare(strict_types=1);

use function Cake\Core\env;

return [
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),
    'Security' => [
        'salt' => env('SECURITY_SALT', 'change-this-development-salt'),
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
