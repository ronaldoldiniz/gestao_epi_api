<?php
return [
    'app' => [
        'env' => getenv('APP_ENV') ?: 'production',
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'secret_key' => getenv('APP_SECRET_KEY') ?: 'CHANGE_ME',
        'token_ttl' => (int)(getenv('APP_TOKEN_TTL') ?: 43200),
    ],
    'security' => [
        'max_login_attempts' => (int)(getenv('MAX_LOGIN_ATTEMPTS') ?: 5),
        'lockout_time' => (int)(getenv('LOCKOUT_TIME') ?: 900),
        'max_pin_attempts' => (int)(getenv('MAX_PIN_ATTEMPTS') ?: 3),
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'dbname' => getenv('DB_NAME') ?: 'gestao_epi',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
];
