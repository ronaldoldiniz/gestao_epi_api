<?php
declare(strict_types=1);

namespace Security;

class PasswordService {
    /**
     * Gera um salt criptograficamente aleatório e seguro em formato hexadecimal (32 caracteres / 16 bytes)
     */
    public static function generateSalt(): string {
        return bin2hex(random_bytes(16));
    }

    /**
     * Gera o hash SHA-256 a partir da senha informada concatendo o salt
     */
    public static function hash(string $password, string $salt): string {
        return hash('sha256', $salt . $password);
    }

    /**
     * Valida se a senha informada corresponde ao hash seguro armazenado
     */
    public static function verify(string $password, string $salt, string $hash): bool {
        $calculatedHash = self::hash($password, $salt);
        return hash_equals($hash, $calculatedHash);
    }
}
