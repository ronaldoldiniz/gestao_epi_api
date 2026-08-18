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
     * Busca os itens de uma entrega específica, preenchendo os snapshots
     * com fallbacks para "NÃO REGISTRADO" se for entrega antiga.
     */
    public function findByEntregaId(int $entrId): array {
        $sql = "SELECT i.*, e.epi_nome, e.epi_ca, e.epi_validade_uso_dias, e.epi_fabricante
                FROM itens_entrega i
                JOIN epis e ON i.epi_id = e.epi_id
                WHERE i.entr_id = :entr_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':entr_id' => $entrId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['item_epi_nome_snapshot'] = $row['item_epi_nome_snapshot'] ?? $row['epi_nome'];
            $row['item_epi_fabricante_snapshot'] = $row['item_epi_fabricante_snapshot'] ?? $row['epi_fabricante'];
            $row['item_epi_ca_snapshot'] = $row['item_epi_ca_snapshot'] ?? ($row['epi_ca'] ?? 'NÃO REGISTRADO');
            $row['item_epi_validade_ca_snapshot'] = $row['item_epi_validade_ca_snapshot'] ?? 'NÃO REGISTRADO';
            $row['item_epi_vida_util_snapshot'] = $row['item_epi_vida_util_snapshot'] ?? ($row['epi_validade_uso_dias'] ?? 'NÃO REGISTRADO');
            $row['item_epi_valor_snapshot'] = $row['item_epi_valor_snapshot'] ?? '0.00';
            $row['item_epi_descricao_snapshot'] = $row['item_epi_descricao_snapshot'] ?? 'NÃO REGISTRADO';
            $row['item_epi_modelo_snapshot'] = $row['item_epi_modelo_snapshot'] ?? 'NÃO REGISTRADO';
            $row['item_epi_origem_preco_snapshot'] = $row['item_epi_origem_preco_snapshot'] ?? 'NÃO REGISTRADO';
            $row['item_epi_localizacao_snapshot'] = $row['item_epi_localizacao_snapshot'] ?? 'NÃO REGISTRADO';
            $row['item_epi_validade_produto_snapshot'] = $row['item_epi_validade_produto_snapshot'] ?? 'NÃO REGISTRADO';
        }
        return $rows;
    }

    /**
     * Busca um item específico pelo ID com fallbacks
     */
    public function findById(int $id): ?array {
        $sql = "SELECT i.*, e.epi_nome, e.epi_ca, e.epi_validade_uso_dias, e.epi_fabricante, e.epi_modelo, e.epi_origem_preco, e.epi_localizacao
                FROM itens_entrega i 
                JOIN epis e ON i.epi_id = e.epi_id
                WHERE i.item_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['item_epi_nome_snapshot'] = $row['item_epi_nome_snapshot'] ?? $row['epi_nome'];
        $row['item_epi_fabricante_snapshot'] = $row['item_epi_fabricante_snapshot'] ?? $row['epi_fabricante'];
        $row['item_epi_ca_snapshot'] = $row['item_epi_ca_snapshot'] ?? ($row['epi_ca'] ?? 'NÃO REGISTRADO');
        $row['item_epi_validade_ca_snapshot'] = $row['item_epi_validade_ca_snapshot'] ?? 'NÃO REGISTRADO';
        $row['item_epi_vida_util_snapshot'] = $row['item_epi_vida_util_snapshot'] ?? ($row['epi_validade_uso_dias'] ?? 'NÃO REGISTRADO');
        $row['item_epi_valor_snapshot'] = $row['item_epi_valor_snapshot'] ?? '0.00';
        $row['item_epi_descricao_snapshot'] = $row['item_epi_descricao_snapshot'] ?? 'NÃO REGISTRADO';
        $row['item_epi_modelo_snapshot'] = $row['item_epi_modelo_snapshot'] ?? ($row['epi_modelo'] ?? 'NÃO REGISTRADO');
        $row['item_epi_origem_preco_snapshot'] = $row['item_epi_origem_preco_snapshot'] ?? ($row['epi_origem_preco'] ?? 'NÃO REGISTRADO');
        $row['item_epi_localizacao_snapshot'] = $row['item_epi_localizacao_snapshot'] ?? ($row['epi_localizacao'] ?? 'NÃO REGISTRADO');

        return $row;
    }

    /**
     * Insere um item na entrega salvando snapshot (usado dentro de transação)
     */
    public function create(array $data): int {
        $sql = "INSERT INTO itens_entrega (
                    entr_id, epi_id, item_quantidade, item_status, item_numero_lote, item_tamanho,
                    item_epi_nome_snapshot, item_epi_descricao_snapshot, item_epi_fabricante_snapshot,
                    item_epi_modelo_snapshot, item_epi_ca_snapshot, item_epi_validade_ca_snapshot,
                    item_epi_vida_util_snapshot, item_epi_valor_snapshot, item_epi_origem_preco_snapshot,
                    item_epi_localizacao_snapshot
                ) VALUES (
                    :entr_id, :epi_id, :quantidade, :status, :numero_lote, :tamanho,
                    :nome, :descricao, :fabricante, :modelo, :ca, :validade_ca,
                    :vida_util, :valor, :origem_preco, :localizacao
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':entr_id' => $data['entr_id'],
            ':epi_id' => $data['epi_id'],
            ':quantidade' => (int)$data['item_quantidade'],
            ':status' => $data['item_status'] ?? 'ENTREGUE',
            ':numero_lote' => $data['item_numero_lote'] ?? null,
            ':tamanho' => $data['item_tamanho'] ?? null,
            ':nome' => $data['item_epi_nome_snapshot'] ?? null,
            ':descricao' => $data['item_epi_descricao_snapshot'] ?? null,
            ':fabricante' => $data['item_epi_fabricante_snapshot'] ?? null,
            ':modelo' => $data['item_epi_modelo_snapshot'] ?? null,
            ':ca' => $data['item_epi_ca_snapshot'] ?? null,
            ':validade_ca' => $data['item_epi_validade_ca_snapshot'] ?? null,
            ':vida_util' => isset($data['item_epi_vida_util_snapshot']) ? (int)$data['item_epi_vida_util_snapshot'] : null,
            ':valor' => $data['item_epi_valor_snapshot'] ?? 0.00,
            ':origem_preco' => $data['item_epi_origem_preco_snapshot'] ?? null,
            ':localizacao' => $data['item_epi_localizacao_snapshot'] ?? null
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Atualiza dados históricos de um item (Correção Administrativa)
     */
    public function updateHistoricalData(int $itemId, array $updates): bool {
        $fields = [];
        $params = [':item_id' => $itemId];

        foreach ($updates as $key => $value) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE itens_entrega SET " . implode(", ", $fields) . " WHERE item_id = :item_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Registra a devolução de um item específico
     */
    public function devolver(int $id, string $status = 'DEVOLVIDO', ?string $motivo = null, ?string $condicao = null, ?string $destino = null, ?string $obs = null): bool {
        $sql = "UPDATE itens_entrega 
                SET item_data_devolucao = NOW(), 
                    item_status = :status,
                    item_devolucao_motivo = :motivo,
                    item_devolucao_condicao = :condicao,
                    item_devolucao_destino = :destino,
                    item_devolucao_obs = :obs
                WHERE item_id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':status' => $status,
            ':motivo' => $motivo,
            ':condicao' => $condicao,
            ':destino' => $destino,
            ':obs' => $obs
        ]);
    }

    /**
     * Registra a devolução vinculada de um item simultaneamente a uma nova entrega
     */
    public function devolverVinculado(int $id, array $params): bool {
        $sql = "UPDATE itens_entrega 
                SET item_data_devolucao = :data_devolucao, 
                    item_status = :status,
                    item_devolucao_motivo = :motivo,
                    item_devolucao_condicao = :condicao,
                    item_devolucao_destino = :destino,
                    item_devolucao_obs = :obs,
                    item_devolucao_vinculo_entrega_id = :vinculo_entrega_id,
                    item_devolucao_vinculo_item_id = :vinculo_item_id,
                    item_devolucao_tipo_operacao = 'DEVOLUCAO_VINCULADA_A_NOVA_ENTREGA'
                WHERE item_id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':data_devolucao' => $params['data_devolucao'],
            ':status' => $params['status'] ?? 'DEVOLVIDO',
            ':motivo' => $params['motivo'],
            ':condicao' => $params['condicao'],
            ':destino' => $params['destino'],
            ':obs' => $params['obs'] ?? null,
            ':vinculo_entrega_id' => $params['vinculo_entrega_id'],
            ':vinculo_item_id' => $params['vinculo_item_id']
        ]);
    }

    /**
     * Cancela todos os itens de uma entrega cancelada
     */
    public function cancelByEntregaId(int $entrId): bool {
        $sql = "UPDATE itens_entrega SET item_status = 'CANCELADO' WHERE entr_id = :entr_id AND item_status = 'ENTREGUE'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':entr_id' => $entrId]);
    }
}
