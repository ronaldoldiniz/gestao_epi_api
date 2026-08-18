<?php
declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use Exception;

class ItemEntrega {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Busca os itens de uma entrega específica
     */
    public function findByEntregaId(int $entrId): array {
        $sql = "SELECT i.*, e.epi_nome, e.epi_ca, e.epi_validade_uso_dias
                FROM Itens_Entrega i
                JOIN EPIs e ON i.epi_id = e.epi_id
                WHERE i.entr_id = :entr_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':entr_id' => $entrId]);
        return $stmt->fetchAll();
    }

    /**
     * Busca um item específico pelo ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM Itens_Entrega WHERE item_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();
        return $item ?: null;
    }

    /**
     * Insere um item na entrega (usado dentro de transação)
     */
    public function create(array $data): int {
        $sql = "INSERT INTO Itens_Entrega (entr_id, epi_id, item_quantidade, item_status, item_numero_lote, item_tamanho, item_motivo_entrega)
                VALUES (:entr_id, :epi_id, :quantidade, :status, :lote, :tamanho, :motivo_entrega)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':entr_id' => $data['entr_id'],
            ':epi_id' => $data['epi_id'],
            ':quantidade' => (int)$data['item_quantidade'],
            ':status' => $data['item_status'] ?? 'ENTREGUE',
            ':lote' => $data['item_numero_lote'] ?? null,
            ':tamanho' => $data['item_tamanho'] ?? null,
            ':motivo_entrega' => $data['item_motivo_entrega'] ?? null
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Registra a devolução de um item específico
     */
    public function devolver(int $id, string $status = 'DEVOLVIDO', array $detalhes = []): bool {
        $sql = "UPDATE Itens_Entrega 
                SET item_data_devolucao = NOW(), 
                    item_status = :status,
                    item_devolucao_motivo = :motivo,
                    item_devolucao_condicao = :condicao,
                    item_devolucao_destino = :destino,
                    item_devolucao_obs = :obs,
                    item_devolucao_vinculo_entrega_id = :vinculo_entr_id,
                    item_devolucao_vinculo_item_id = :vinculo_item_id,
                    item_devolucao_tipo_operacao = :tipo_operacao
                WHERE item_id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':status' => $status,
            ':motivo' => $detalhes['motivo'] ?? null,
            ':condicao' => $detalhes['condicao'] ?? null,
            ':destino' => $detalhes['destino'] ?? null,
            ':obs' => $detalhes['observacao'] ?? null,
            ':vinculo_entr_id' => $detalhes['vinculo_entrega_id'] ?? null,
            ':vinculo_item_id' => $detalhes['vinculo_item_id'] ?? null,
            ':tipo_operacao' => $detalhes['tipo_operacao'] ?? 'DEVOLUCAO_VINCULADA_A_NOVA_ENTREGA'
        ]);
    }

    /**
     * Cancela todos os itens de uma entrega cancelada
     */
    public function cancelByEntregaId(int $entrId): bool {
        $sql = "UPDATE Itens_Entrega SET item_status = 'CANCELADO' WHERE entr_id = :entr_id AND item_status = 'ENTREGUE'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':entr_id' => $entrId]);
    }
}
