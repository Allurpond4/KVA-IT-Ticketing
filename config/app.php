<?php
declare(strict_types=1);

return [
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'session_name' => getenv('SESSION_NAME') ?: 'kva_it_session',
    'database' => [
        'driver' => getenv('DB_DRIVER') ?: 'sqlsrv',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '1433',
        'database' => getenv('DB_DATABASE') ?: '',
        'username' => getenv('DB_USERNAME') ?: '',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'log_level' => getenv('LOG_LEVEL') ?: 'info',
];
