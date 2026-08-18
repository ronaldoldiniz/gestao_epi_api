<?php
declare(strict_types=1);

namespace Core;

class Response {
    /**
     * Envia uma resposta JSON padronizada e encerra a execução
     */
    public static function json(bool $success, string $message, $data = null, int $statusCode = 200): void {
        // Garante que nenhum conteúdo parcial foi enviado antes
        if (ob_get_level() > 0) {
            ob_clean();
        }

        // Configuração dos cabeçalhos HTTP recomendados de segurança
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        
        // Define o código HTTP da resposta
        http_response_code($statusCode);

        $payload = [
            'success' => $success,
            'message' => $message,
            'data' => $data
        ];

        if (is_array($data) && isset($data['_is_custom_payload']) && $data['_is_custom_payload']) {
            unset($data['_is_custom_payload']);
            $payload = array_merge($payload, $data);
            // If only _is_custom_payload was present, set data to null
            if (!array_key_exists('data', $data)) {
                $payload['data'] = null;
            }
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
