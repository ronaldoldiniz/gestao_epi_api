<?php
/**
 * Script temporário de migração - REMOVER APÓS EXECUÇÃO
 */
header('Content-Type: application/json');

try {
    $config = require __DIR__ . '/config/config.php';
    $dbConfig = $config['db'];
    
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ];
    
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
    
    // Dropar e recriar para garantir estrutura limpa
    $db->exec("DROP TABLE IF EXISTS termos_responsabilidade");
    
    $db->exec("CREATE TABLE termos_responsabilidade (
        termo_id int(11) NOT NULL AUTO_INCREMENT,
        termo_codigo varchar(50) NOT NULL,
        termo_versao varchar(20) NOT NULL,
        termo_titulo varchar(100) NOT NULL,
        termo_texto_completo text NOT NULL,
        termo_data_inicio_vigencia datetime NOT NULL,
        termo_data_fim_vigencia datetime DEFAULT NULL,
        termo_status varchar(20) NOT NULL DEFAULT 'ATIVO',
        usu_cadastro_id int(11) NOT NULL,
        termo_data_hora_cadastro datetime NOT NULL,
        PRIMARY KEY (termo_id),
        KEY usu_cadastro_id (usu_cadastro_id),
        CONSTRAINT fk_termos_usu FOREIGN KEY (usu_cadastro_id) REFERENCES usuarios (usu_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    
    // Inserir termo padrão
    $stmtInsert = $db->prepare("INSERT INTO termos_responsabilidade (termo_codigo, termo_versao, termo_titulo, termo_texto_completo, termo_data_inicio_vigencia, termo_status, usu_cadastro_id, termo_data_hora_cadastro) VALUES (:codigo, :versao, :titulo, :texto, NOW(), 'ATIVO', 1, NOW())");
    $stmtInsert->execute([
        ':codigo' => 'TERMO_EPI',
        ':versao' => '2.0',
        ':titulo' => 'Termo de Responsabilidade Eletrônico de EPI',
        ':texto' => 'Declaro estar recebendo, gratuitamente e sem qualquer ônus, os Equipamentos de Proteção Individual – EPIs discriminados neste termo. Declaro estar ciente da obrigatoriedade de sua utilização durante a execução das atividades para as quais foram fornecidos, conforme as orientações da empresa e a legislação aplicável. A validação por senha ou PIN confirma o recebimento dos itens discriminados, a ciência do conteúdo deste termo e a aceitação das responsabilidades nele descritas.'
    ]);
    
    // Listar tabelas
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tabela termos_responsabilidade criada e termo padrão inserido.',
        'tables_in_db' => $tables
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
}
