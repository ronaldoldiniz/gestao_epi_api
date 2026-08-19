<?php
header('Content-Type: application/json');

// Helper to mask values
function get_masked_env() {
    $res = [];
    // Read all keys from $_ENV, $_SERVER and getenv()
    $all_keys = array_unique(array_merge(
        array_keys($_ENV),
        array_keys($_SERVER),
        ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'AES_KEY', 'HMAC_KEY', 'APP_ENV', 'APP_DEBUG', 'APP_SECRET_KEY']
    ));
    
    foreach ($all_keys as $key) {
        // Skip common system server variables that are noise
        if (strpos($key, 'HTTP_') === 0 || strpos($key, 'REQUEST_') === 0 || strpos($key, 'SERVER_') === 0 || strpos($key, 'SCRIPT_') === 0 || strpos($key, 'GATEWAY_') === 0 || strpos($key, 'DOCUMENT_') === 0 || strpos($key, 'PATH_') === 0 || in_array($key, ['QUERY_STRING', 'REMOTE_ADDR', 'REMOTE_PORT', 'REQUEST_METHOD', 'REQUEST_URI', 'SCRIPT_FILENAME', 'SCRIPT_NAME'])) {
            continue;
        }
        
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?? null;
        if ($val === null || $val === false) {
            $res[$key] = 'NOT_SET';
        } else {
            $is_secret = preg_match('/(PASS|KEY|SECRET|HMAC|TOKEN)/i', $key);
            if ($is_secret) {
                $res[$key] = 'SET (len: ' . strlen((string)$val) . ')';
            } else {
                $res[$key] = $val;
            }
        }
    }
    return $res;
}

$db_connection_error = null;
$db_connection_success = false;

$host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$port = $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$dbname = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: 'gestao_epi';
$username = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$password = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?: '';

try {
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
    'all_masked_env' => get_masked_env(),
    'config_file_exists' => file_exists(__DIR__ . '/config/config.php'),
    'example_file_exists' => file_exists(__DIR__ . '/config/config.example.php'),
    'db_connection_success' => $db_connection_success,
    'db_connection_error' => $db_connection_error
], JSON_PRETTY_PRINT);
