<?php
declare(strict_types=1);

namespace Security;

use Exception;

class CryptoService {
    private static ?string $aesKey = null;
    private static ?string $hmacKey = null;

    /**
     * Inicializa as chaves secretas a partir de variáveis de ambiente ou arquivo de configuração
     */
    private static function initKeys(): void {
        if (self::$aesKey !== null && self::$hmacKey !== null) {
            return;
        }

        // Tenta ler do ambiente (.env)
        $aesHex = $_ENV['AES_KEY'] ?? getenv('AES_KEY') ?? null;
        $hmacHex = $_ENV['HMAC_KEY'] ?? getenv('HMAC_KEY') ?? null;

        // Fallback para arquivo de configuração se não achar no ambiente
        if (!$aesHex || !$hmacHex) {
            $configFile = dirname(__DIR__) . '/config/config.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
                $aesHex = $aesHex ?: ($config['security']['aes_key'] ?? null);
                $hmacHex = $hmacHex ?: ($config['security']['hmac_key'] ?? null);
            }
        }

        if (!$aesHex || !$hmacHex) {
            throw new Exception("Chaves de segurança AES_KEY ou HMAC_KEY não configuradas no ambiente.");
        }

        // Converte as chaves de hexadecimal para binário (32 bytes para AES-256)
        self::$aesKey = hex2bin($aesHex);
        self::$hmacKey = hex2bin($hmacHex);

        if (self::$aesKey === false || strlen(self::$aesKey) !== 32) {
            throw new Exception("AES_KEY inválida. Deve ser uma chave hexadecimal de 256 bits (64 caracteres hexadecimais).");
        }
        if (self::$hmacKey === false) {
            throw new Exception("HMAC_KEY inválida.");
        }
    }

    /**
     * Encripta um texto puro usando AES-256-GCM
     * Retorna array com ['ciphertext', 'iv', 'tag'] codificados em Base64
     */
    public static function encrypt(string $plainText): array {
        self::initKeys();

        // IV de 12 bytes recomendado para GCM
        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plainText,
            'aes-256-gcm',
            self::$aesKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16 // Tag de 16 bytes
        );

        if ($ciphertext === false) {
            throw new Exception("Falha na encriptação dos dados.");
        }

        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag)
        ];
    }

    /**
     * Descriptografa dados usando AES-256-GCM
     */
    public static function decrypt(string $ciphertextBase64, string $ivBase64, string $tagBase64): string {
        self::initKeys();

        $ciphertext = base64_decode($ciphertextBase64);
        $iv = base64_decode($ivBase64);
        $tag = base64_decode($tagBase64);

        $plainText = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            self::$aesKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plainText === false) {
            throw new Exception("Falha na descriptografia. Chave incorreta ou dados adulterados.");
        }

        return $plainText;
    }

    /**
     * Normaliza e gera o hash blind index (lookup) do CPF usando HMAC-SHA-256
     */
    public static function generateLookup(string $value): string {
        self::initKeys();

        // Normalização: remove formatação (pontos, traços e espaços)
        $normalized = preg_replace('/\D/', '', $value);

        // Gera HMAC-SHA-256 da string normalizada
        return hash_hmac('sha256', $normalized, self::$hmacKey);
    }
}
