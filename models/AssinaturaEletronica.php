<?php
declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use Exception;

class AssinaturaEletronica {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Busca a assinatura eletrônica pelo ID do Funcionário
     */
    public function findByFuncionarioId(int $funId): ?array {
        $sql = "SELECT * FROM Assinatura_Eletronica WHERE fun_id = :fun_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':fun_id' => $funId]);
        $assinatura = $stmt->fetch();
        return $assinatura ?: null;
    }

    /**
     * Busca a assinatura eletrônica pelo ID próprio
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM Assinatura_Eletronica WHERE ass_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $assinatura = $stmt->fetch();
        return $assinatura ?: null;
    }

    /**
     * Cria uma nova assinatura eletrônica com PIN criptografado
     */
    public function create(array $data): int {
        // Gera um salt aleatório para manter compatibilidade com o campo do banco,
        // embora o password_hash() já gerencie seu próprio salt interno de forma segura.
        $salt = bin2hex(random_bytes(16));
        $hash = password_hash($data['pin'], PASSWORD_DEFAULT);

        $sql = "INSERT INTO Assinatura_Eletronica (
                    fun_id, usu_id, ass_senha_hash, ass_salt, ass_status, 
                    ass_data_cadastro, ass_tentativas_falha
                ) VALUES (
                    :fun_id, :usu_id, :senha_hash, :salt, 'ATIVO', 
                    NOW(), 0
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':fun_id' => $data['fun_id'],
            ':usu_id' => $data['usu_id'],
            ':senha_hash' => $hash,
            ':salt' => $salt
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Atualiza o PIN da assinatura eletrônica
     */
    public function updatePin(int $id, string $newPin): bool {
        $hash = password_hash($newPin, PASSWORD_DEFAULT);
        $sql = "UPDATE Assinatura_Eletronica 
                SET ass_senha_hash = :senha_hash, 
                    ass_status = 'ATIVO', 
                    ass_tentativas_falha = 0, 
                    ass_data_bloqueio = NULL, 
                    ass_motivo_bloqueio = NULL 
                WHERE ass_id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':senha_hash' => $hash
        ]);
    }

    /**
     * Registra uso bem-sucedido atualizando o timestamp
     */
    public function registerUse(int $id): bool {
        $sql = "UPDATE Assinatura_Eletronica SET ass_ultimo_uso = NOW(), ass_tentativas_falha = 0 WHERE ass_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Incrementa tentativas de falha e bloqueia se necessário
     */
    public function incrementFailAttempts(int $id, int $maxAttempts): int {
        $sql = "UPDATE Assinatura_Eletronica SET ass_tentativas_falha = ass_tentativas_falha + 1 WHERE ass_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        $assinatura = $this->findById($id);
        if ($assinatura && (int)$assinatura['ass_tentativas_falha'] >= $maxAttempts) {
            $this->lockSignature($id, "Excesso de tentativas inválidas do PIN (" . $assinatura['ass_tentativas_falha'] . ")");
        }

        return $assinatura ? (int)$assinatura['ass_tentativas_falha'] : 0;
    }

    /**
     * Bloqueia a assinatura eletrônica
     */
    public function lockSignature(int $id, string $motivo): bool {
        $sql = "UPDATE Assinatura_Eletronica 
                SET ass_status = 'BLOQUEADO', 
                    ass_data_bloqueio = NOW(), 
                    ass_motivo_bloqueio = :motivo 
                WHERE ass_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':motivo' => $motivo
        ]);
    }

    /**
     * Desbloqueia a assinatura eletrônica resetando as falhas
     */
    public function unlockSignature(int $id): bool {
        $sql = "UPDATE Assinatura_Eletronica 
                SET ass_status = 'ATIVO', 
                    ass_tentativas_falha = 0, 
                    ass_data_bloqueio = NULL, 
                    ass_motivo_bloqueio = NULL 
                WHERE ass_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
