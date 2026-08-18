<?php
declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use Exception;

class Epi {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Lista todos os EPIs
     */
    public function findAll(): array {
        $sql = "SELECT * FROM EPIs ORDER BY epi_nome ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Busca EPI pelo ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM EPIs WHERE epi_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $epi = $stmt->fetch();
        return $epi ?: null;
    }

    /**
     * Cadastra um novo EPI e opcionalmente insere no histórico de preços
     */
    public function create(array $data, int $usuId): int {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO EPIs (
                        epi_nome, epi_ca, epi_vencimento_ca, epi_fabricante, 
                        epi_validade_uso_dias, epi_status, epi_valor, epi_origem_preco, epi_localizacao
                    ) VALUES (
                        :nome, :ca, :vencimento_ca, :fabricante, 
                        :validade_uso, :status, :valor, :origem_preco, :localizacao
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nome' => $data['epi_nome'],
                ':ca' => $data['epi_ca'],
                ':vencimento_ca' => $data['epi_vencimento_ca'],
                ':fabricante' => $data['epi_fabricante'],
                ':validade_uso' => (int)$data['epi_validade_uso_dias'],
                ':status' => $data['epi_status'] ?? 'ATIVO',
                ':valor' => $data['epi_valor'] ?? 0.00,
                ':origem_preco' => $data['epi_origem_preco'] ?? 'COMPRA_DIRETA',
                ':localizacao' => $data['epi_localizacao'] ?? null
            ]);

            $epiId = (int)$this->db->lastInsertId();

            // Grava o histórico inicial de preços
            $this->addPriceHistory([
                'epi_id' => $epiId,
                'usu_id' => $usuId,
                'hist_valor' => $data['epi_valor'] ?? 0.00,
                'hist_origem' => $data['epi_origem_preco'] ?? 'COMPRA_DIRETA',
                'hist_nota_fiscal' => $data['hist_nota_fiscal'] ?? null,
                'hist_fornecedor' => $data['hist_fornecedor'] ?? null
            ]);

            $this->db->commit();
            return $epiId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza dados de um EPI, registrando novo histórico caso o valor seja alterado
     */
    public function update(int $id, array $data, int $usuId): bool {
        try {
            $this->db->beginTransaction();

            // Busca valor atual para comparar
            $current = $this->findById($id);
            if (!$current) {
                $this->db->rollBack();
                return false;
            }

            $fields = [];
            $params = [':id' => $id];

            $updatableFields = [
                'epi_nome' => 'nome',
                'epi_ca' => 'ca',
                'epi_vencimento_ca' => 'vencimento_ca',
                'epi_fabricante' => 'fabricante',
                'epi_validade_uso_dias' => 'validade_uso',
                'epi_status' => 'status',
                'epi_valor' => 'valor',
                'epi_origem_preco' => 'origem_preco',
                'epi_localizacao' => 'localizacao'
            ];

            foreach ($updatableFields as $dbField => $paramKey) {
                if (isset($data[$dbField])) {
                    $fields[] = "{$dbField} = :{$paramKey}";
                    $params[":{$paramKey}"] = $data[$dbField];
                }
            }

            if (empty($fields)) {
                $this->db->rollBack();
                return false;
            }

            $sql = "UPDATE EPIs SET " . implode(", ", $fields) . " WHERE epi_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Se o valor ou a origem mudou, registra o histórico de preços
            $valueChanged = isset($data['epi_valor']) && (float)$data['epi_valor'] !== (float)$current['epi_valor'];
            $originChanged = isset($data['epi_origem_preco']) && $data['epi_origem_preco'] !== $current['epi_origem_preco'];

            if ($valueChanged || $originChanged) {
                $this->addPriceHistory([
                    'epi_id' => $id,
                    'usu_id' => $usuId,
                    'hist_valor' => isset($data['epi_valor']) ? (float)$data['epi_valor'] : (float)$current['epi_valor'],
                    'hist_origem' => $data['epi_origem_preco'] ?? $current['epi_origem_preco'],
                    'hist_nota_fiscal' => $data['hist_nota_fiscal'] ?? null,
                    'hist_fornecedor' => $data['hist_fornecedor'] ?? null
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Efetua exclusão lógica, inativando o EPI
     */
    public function delete(int $id): bool {
        $sql = "UPDATE EPIs SET epi_status = 'INATIVO' WHERE epi_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Busca EPIs com C.A. vencido em relação à data atual
     */
    public function findExpiredCa(): array {
        $sql = "SELECT * FROM EPIs WHERE epi_vencimento_ca < CURDATE() AND epi_status != 'INATIVO'";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Busca EPIs com C.A. próximo do vencimento
     */
    public function findNextExpirationCa(int $days = 30): array {
        $sql = "SELECT * FROM EPIs 
                WHERE epi_vencimento_ca >= CURDATE() 
                  AND epi_vencimento_ca <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
                  AND epi_status != 'INATIVO'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':days' => $days]);
        return $stmt->fetchAll();
    }

    /**
     * Adiciona registro na tabela Historico_Preco_EPI
     */
    private function addPriceHistory(array $data): int {
        $sql = "INSERT INTO Historico_Preco_EPI (
                    epi_id, usu_id, hist_valor, hist_data_vigencia, hist_origem, hist_nota_fiscal, hist_fornecedor
                ) VALUES (
                    :epi_id, :usu_id, :hist_valor, NOW(), :hist_origem, :hist_nota_fiscal, :hist_fornecedor
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':epi_id' => $data['epi_id'],
            ':usu_id' => $data['usu_id'],
            ':hist_valor' => $data['hist_valor'],
            ':hist_origem' => $data['hist_origem'],
            ':hist_nota_fiscal' => $data['hist_nota_fiscal'],
            ':hist_fornecedor' => $data['hist_fornecedor']
        ]);

        return (int)$this->db->lastInsertId();
    }
}
