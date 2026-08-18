<?php
declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use Exception;

class EntregaEpi {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Lista todas as entregas de EPIs com o nome do Funcionário e Usuário
     */
    public function findAll(): array {
        $sql = "SELECT e.*, f.fun_nome, u.usu_login 
                FROM Entrega_EPIs e
                JOIN Funcionarios f ON e.fun_id = f.fun_id
                JOIN Usuarios u ON e.usu_id = u.usu_id
                ORDER BY e.entr_data_entrega DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Busca uma entrega pelo ID com detalhes
     */
    public function findById(int $id): ?array {
        $sql = "SELECT e.*, f.fun_nome, u.usu_login 
                FROM Entrega_EPIs e
                JOIN Funcionarios f ON e.fun_id = f.fun_id
                JOIN Usuarios u ON e.usu_id = u.usu_id
                WHERE e.entr_id = :id 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $entrega = $stmt->fetch();
        return $entrega ?: null;
    }

    /**
     * Lista entregas de um funcionário específico
     */
    public function findByFuncionarioId(int $funId): array {
        $sql = "SELECT e.*, u.usu_login 
                FROM Entrega_EPIs e
                JOIN Usuarios u ON e.usu_id = u.usu_id
                WHERE e.fun_id = :fun_id
                ORDER BY e.entr_data_entrega DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':fun_id' => $funId]);
        return $stmt->fetchAll();
    }

    /**
     * Insere o cabeçalho da entrega de EPIs (usado dentro de transação)
     */
    public function create(array $data): int {
        $sql = "INSERT INTO Entrega_EPIs (
                    fun_id, usu_id, ass_id, entr_data_entrega, entr_hash_assinatura, 
                    entr_termo_ciencia, entr_status, entr_status_sinc, entr_validacao_senha, entr_motivo,
                    entr_client_operation_id
                ) VALUES (
                    :fun_id, :usu_id, :ass_id, NOW(), :hash_assinatura, 
                    :termo_ciencia, :status, :status_sinc, :validacao_senha, :motivo,
                    :client_operation_id
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':fun_id' => $data['fun_id'],
            ':usu_id' => $data['usu_id'],
            ':ass_id' => $data['ass_id'],
            ':hash_assinatura' => $data['entr_hash_assinatura'],
            ':termo_ciencia' => $data['entr_termo_ciencia'] ?? 'SIM',
            ':status' => $data['entr_status'] ?? 'FINALIZADA',
            ':status_sinc' => $data['entr_status_sinc'] ?? 'SINCRONIZADO',
            ':validacao_senha' => $data['entr_validacao_senha'] ?? 'VALIDADA',
            ':motivo' => $data['entr_motivo'] ?? null,
            ':client_operation_id' => $data['client_operation_id'] ?? null
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Busca uma entrega pelo client_operation_id (usado para reconciliação)
     */
    public function findByClientOperationId(string $clientOperationId): ?array {
        $sql = "SELECT e.*, f.fun_nome, u.usu_login 
                FROM Entrega_EPIs e
                JOIN Funcionarios f ON e.fun_id = f.fun_id
                JOIN Usuarios u ON e.usu_id = u.usu_id
                WHERE e.entr_client_operation_id = :client_operation_id 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':client_operation_id' => $clientOperationId]);
        $entrega = $stmt->fetch();
        return $entrega ?: null;
    }

    /**
     * Cancela uma entrega de EPIs
     */
    public function cancel(int $id, string $motivo, int $usuId): bool {
        $sql = "UPDATE Entrega_EPIs 
                SET entr_status = 'CANCELADA', 
                    entr_motivo = :motivo,
                    usu_id = :usu_id 
                WHERE entr_id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':motivo' => $motivo,
            ':usu_id' => $usuId
        ]);
    }
}
