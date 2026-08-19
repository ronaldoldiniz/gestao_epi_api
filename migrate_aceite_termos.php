<?php
/**
 * Migração: Aceite de Termos de Uso
 * Acessar uma única vez via: https://gestao-epi-api.onrender.com/migrate_aceite_termos.php
 * REMOVER APÓS EXECUÇÃO
 */
header('Content-Type: application/json');

try {
    $config = require __DIR__ . '/config/config.php';
    $dbConfig = $config['db'];

    $dbConfig['host'] = getenv('DB_HOST') ?: $dbConfig['host'];
    $dbConfig['port'] = getenv('DB_PORT') ?: $dbConfig['port'];
    $dbConfig['dbname'] = getenv('DB_NAME') ?: $dbConfig['dbname'];
    $dbConfig['username'] = getenv('DB_USER') ?: $dbConfig['username'];
    $dbConfig['password'] = getenv('DB_PASS') ?: $dbConfig['password'];

    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=utf8mb4";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ]);

    $resultado = [];

    // Verificar se a coluna já existe
    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'usu_aceite_termos'");
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN usu_aceite_termos TINYINT(1) NOT NULL DEFAULT 0");
        $resultado[] = "Coluna usu_aceite_termos adicionada.";
    } else {
        $resultado[] = "Coluna usu_aceite_termos já existe.";
    }

    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'usu_data_aceite_termos'");
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN usu_data_aceite_termos BIGINT(20) NULL DEFAULT NULL");
        $resultado[] = "Coluna usu_data_aceite_termos adicionada.";
    } else {
        $resultado[] = "Coluna usu_data_aceite_termos já existe.";
    }

    echo json_encode([
        'success' => true,
        'message' => 'Migração de aceite de termos concluída.',
        'detalhes' => $resultado
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
}
