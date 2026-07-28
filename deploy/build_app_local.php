<?php
declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php build_app_local.php <output-path>\n");
    exit(2);
}

$required = [
    'APP_URL',
    'DATABASE_URL',
    'SECURITY_SALT',
];

$values = [];
foreach ($required as $name) {
    $value = getenv($name);
    if ($value === false || $value === '') {
        fwrite(STDERR, "Required environment variable is missing: {$name}\n");
        exit(2);
    }
    $values[$name] = $value;
}

$config = [
    'debug' => false,
    'App' => [
        'fullBaseUrl' => $values['APP_URL'],
        'defaultLocale' => 'ja_JP',
        'defaultTimezone' => 'Asia/Tokyo',
        'frontendOrigin' => $values['APP_URL'],
    ],
    'Security' => [
        'salt' => $values['SECURITY_SALT'],
    ],
    'Datasources' => [
        'default' => [
            'url' => $values['DATABASE_URL'],
        ],
    ],
];

$contents = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
if (file_put_contents($argv[1], $contents, LOCK_EX) === false) {
    fwrite(STDERR, "Could not write production configuration.\n");
    exit(1);
}
