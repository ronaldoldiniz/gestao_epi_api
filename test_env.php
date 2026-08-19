<?php
header('Content-Type: application/json');

$env_vars = [
    'DB_HOST' => getenv('DB_HOST'),
    'DB_PORT' => getenv('DB_PORT'),
    'DB_NAME' => getenv('DB_NAME'),
    'DB_USER' => getenv('DB_USER'),
    'DB_PASS_EXISTS' => getenv('DB_PASS') ? 'YES' : 'NO',
    'APP_ENV' => getenv('APP_ENV'),
    'APP_DEBUG' => getenv('APP_DEBUG'),
    'AES_KEY_EXISTS' => getenv('AES_KEY') ? 'YES' : 'NO',
    'HMAC_KEY_EXISTS' => getenv('HMAC_KEY') ? 'YES' : 'NO'
];

$db_connection_error = null;
$db_connection_success = false;

try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'gestao_epi';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ]);
    $db_connection_success = true;
} catch (Exception $e) {
    $db_connection_error = $e->getMessage();
}

echo json_encode([
    'env' => $env_vars,
    'config_file_exists' => file_exists(__DIR__ . '/config/config.php'),
    'example_file_exists' => file_exists(__DIR__ . '/config/config.example.php'),
    'db_connection_success' => $db_connection_success,
    'db_connection_error' => $db_connection_error
], JSON_PRETTY_PRINT);
