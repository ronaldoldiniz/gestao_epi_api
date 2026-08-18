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

            // Sobrescreve config de app com variáveis de ambiente (Render/produção)
            if (getenv('APP_ENV')) $config['app']['env'] = getenv('APP_ENV');
            if (getenv('APP_DEBUG')) $config['app']['debug'] = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
            if (getenv('APP_SECRET_KEY')) $config['app']['secret_key'] = getenv('APP_SECRET_KEY');
            if (getenv('APP_TOKEN_TTL')) $config['app']['token_ttl'] = (int)getenv('APP_TOKEN_TTL');

            $dbConfig = $config['db'];

            // Sobrescreve com variáveis de ambiente (útil para Render/produção)
            $dbConfig['host'] = getenv('DB_HOST') ?: $dbConfig['host'];
            $dbConfig['port'] = getenv('DB_PORT') ?: $dbConfig['port'];
            $dbConfig['dbname'] = getenv('DB_NAME') ?: $dbConfig['dbname'];
            $dbConfig['username'] = getenv('DB_USER') ?: $dbConfig['username'];
            $dbConfig['password'] = getenv('DB_PASS') ?: $dbConfig['password'];
            $dbConfig['charset'] = getenv('DB_CHARSET') ?: $dbConfig['charset'];
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
