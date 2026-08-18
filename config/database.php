<?php
declare(strict_types=1);

namespace Config;

use PDO;
use PDOException;
use Exception;

class Database {
    private static ?PDO $instance = null;

    /**
     * Retorna a instância única da conexão PDO
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $configFile = dirname(__DIR__) . '/config/config.php';
            
            // Tenta carregar do arquivo real ou do de exemplo se o real não existir em ambiente de testes
            if (!file_exists($configFile)) {
                $configFile = dirname(__DIR__) . '/config/config.example.php';
            }

            if (!file_exists($configFile)) {
                throw new Exception("Arquivo de configuração não encontrado.");
            }

            $config = require $configFile;

            $dbConfig = $config['db'];
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['dbname'],
                $dbConfig['charset']
            );

            try {
                self::$instance = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false, // Desabilita emulação de prepared statements por segurança
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $dbConfig['charset']
                ]);
            } catch (PDOException $e) {
                // Em produção não mostramos detalhes da string de conexão ou detalhes internos do banco
                $env = $config['app']['env'] ?? 'production';
                if ($env === 'local') {
                    throw new Exception("Erro de conexão com o banco de dados: " . $e->getMessage());
                } else {
                    throw new Exception("Não foi possível conectar ao banco de dados. Contate o administrador.");
                }
            }
        }

        return self::$instance;
    }
}
