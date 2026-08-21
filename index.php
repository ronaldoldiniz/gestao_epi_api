<?php
declare(strict_types=1);

// Define o fuso horário padrão do sistema
date_default_timezone_set('America/Sao_Paulo');

// ==========================================
// CARREGADOR DE VARIÁVEIS DE AMBIENTE (.env)
// ==========================================
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove aspas se existirem
        if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match('/^\'(.*)\'$/', $value, $matches)) {
            $value = $matches[1];
        }
        
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// ==========================================
// AUTOLOADER MANUAL (Substitui Composer para PSR-4)
// ==========================================
spl_autoload_register(function (string $class) {
    $parts = explode('\\', $class);
    if (count($parts) < 2) {
        return;
    }
    
    $folder = strtolower($parts[0]);
    $className = $parts[1];
    
    // Tratamento especial para o arquivo database.php que está em minúsculo
    if ($folder === 'config' && strtolower($className) === 'database') {
        $className = 'database';
    }
    
    $file = __DIR__ . '/' . $folder . '/' . $className . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Importações do Core
use Core\Response;
use Core\Auth;

// ==========================================
// CAPTURA GLOBAL DE EXCEÇÕES E ERROS (Segurança)
// ==========================================
set_exception_handler(function (\Throwable $exception) {
    // Carrega o debug do config para decidir o nível de detalhes do erro
    $configFile = __DIR__ . '/config/config.php';
    if (!file_exists($configFile)) {
        $configFile = __DIR__ . '/config/config.example.php';
    }
    $config = file_exists($configFile) ? require $configFile : ['app' => ['debug' => false]];
    $isDebug = $config['app']['debug'] ?? false;

    // Registra erro no log interno do servidor (Apache/PHP)
    error_log("Exceção não tratada: " . $exception->getMessage() . " em " . $exception->getFile() . ":" . $exception->getLine());

    if ($isDebug) {
        Response::json(false, "Erro interno: " . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ], 500);
    } else {
        Response::json(false, "Ocorreu um erro interno no servidor.", null, 500);
    }
});

// Captura erros fatais do PHP
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        // Limpa saídas parciais
        if (ob_get_level() > 0) {
            ob_clean();
        }
        error_log("Erro Fatal: " . $error['message'] . " em " . $error['file'] . ":" . $error['line']);
        Response::json(false, "Ocorreu um erro crítico no servidor.", null, 500);
    }
});

// ==========================================
// CONFIGURAÇÕES CORS (Segurança do Navegador)
// ==========================================
// Embora a API seja voltada para Android, definir cabeçalhos limpos evita abusos
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('HTTP/1.1 200 OK');
    exit;
}

// ==========================================
// INICIALIZA AUTENTICAÇÃO E ROTEAMENTO
// ==========================================
Auth::init();

$router = require_once __DIR__ . '/routes/api.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

// Trigger rebuild
