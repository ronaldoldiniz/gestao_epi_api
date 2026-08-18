<?php
declare(strict_types=1);

namespace Core;

use Exception;

class Auth {
    private static ?array $currentUser = null;

    /**
     * Gera um token assinado (formato JWT simplificado) contendo informações do usuário
     */
    public static function generateToken(array $user, string $secretKey, int $ttl): string {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'usu_id' => $user['usu_id'],
            'usu_login' => $user['usu_login'],
            'usu_perfil' => $user['usu_perfil'],
            'exp' => time() + $ttl
        ]);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secretKey, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Valida o token e retorna o payload caso seja válido e não expirado
     */
    public static function validateToken(string $token, string $secretKey): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $parts;

        $signature = self::base64UrlDecode($base64UrlSignature);
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secretKey, true);

        if (!hash_equals($signature, $expectedSignature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);
        if (!$payload || !isset($payload['exp']) || time() > $payload['exp']) {
            return null;
        }

        return $payload;
    }

    /**
     * Extrai o Token Bearer do Header Authorization
     */
    public static function getBearerToken(): ?string {
        $headers = self::getRequestHeaders();
        // Verifica variações comuns de case nos headers HTTP
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if ($authHeader) {
            if (preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Inicializa a autenticação com base na requisição atual
     */
    public static function init(): void {
        $token = self::getBearerToken();
        if ($token) {
            try {
                $configFile = dirname(__DIR__) . '/config/config.php';
                if (!file_exists($configFile)) {
                    $configFile = dirname(__DIR__) . '/config/config.example.php';
                }
                $config = require $configFile;
                $secretKey = $config['app']['secret_key'];
                
                $payload = self::validateToken($token, $secretKey);
                if ($payload) {
                    self::$currentUser = $payload;
                }
            } catch (Exception $e) {
                // Falha silenciosa na inicialização, o requireAuth irá barrar
            }
        }
    }

    /**
     * Exige autenticação e opcionalmente valida se o usuário possui perfil permitido
     */
    public static function requireAuth(array $allowedRoles = []): array {
        if (self::$currentUser === null) {
            Response::json(false, "Usuário não autenticado ou token expirado/inválido.", null, 401);
        }

        // Verifica se o usuário tem a troca de senha obrigatória pendente
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare("SELECT usu_exige_troca_senha FROM Usuarios WHERE usu_id = :id LIMIT 1");
            $stmt->execute([':id' => (int)self::$currentUser['usu_id']]);
            $dbUser = $stmt->fetch();

            if ($dbUser && (int)$dbUser['usu_exige_troca_senha'] === 1) {
                // Determina a URI atual normalizada
                $requestedUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
                if ($requestedUri !== '/' && str_ends_with($requestedUri, '/')) {
                    $requestedUri = rtrim($requestedUri, '/');
                }
                $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
                $basePath = dirname($scriptName);
                $basePath = str_replace('\\', '/', $basePath);
                if ($basePath !== '/' && str_starts_with($requestedUri, $basePath)) {
                    $requestedUri = substr($requestedUri, strlen($basePath));
                }
                if ($requestedUri === '') {
                    $requestedUri = '/';
                }

                // Apenas estes endpoints são permitidos quando a troca de senha é obrigatória
                $allowedEndpoints = [
                    '/auth/alterar-senha-primeiro-acesso',
                    '/auth/me',
                    '/auth/logout'
                ];

                if (!in_array($requestedUri, $allowedEndpoints, true)) {
                    Response::json(false, "Troca de senha obrigatória no primeiro acesso.", null, 403);
                }
            }
        } catch (\Throwable $e) {
            // Em caso de falha de conexão/consulta, continua fluxo padrão ou loga
        }

        if (!empty($allowedRoles) && !in_array(self::$currentUser['usu_perfil'], $allowedRoles, true)) {
            Response::json(false, "Acesso negado. Perfil de usuário insuficiente.", null, 403);
        }

        return self::$currentUser;
    }

    /**
     * Retorna o usuário autenticado na sessão corrente da requisição
     */
    public static function getCurrentUser(): ?array {
        return self::$currentUser;
    }

    private static function base64UrlEncode(string $data): string {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }

    private static function getRequestHeaders(): array {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            } elseif ($name === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($name === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }
        return $headers;
    }
}
