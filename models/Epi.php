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
        $sql = "SELECT * FROM epis ORDER BY epi_nome ASC";
        $stmt = $this->db->query($sql);
        $epis = $stmt->fetchAll();
        foreach ($epis as &$e) {
            if (isset($e['epi_exige_tamanho'])) {
                $e['epi_exige_tamanho'] = (bool)(int)$e['epi_exige_tamanho'];
            }
        }
        return $epis;
    }

    /**
     * Busca EPI pelo ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM epis WHERE epi_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $epi = $stmt->fetch();
        if ($epi && isset($epi['epi_exige_tamanho'])) {
            $epi['epi_exige_tamanho'] = (bool)(int)$epi['epi_exige_tamanho'];
        }
        return $epi ?: null;
    }

    /**
     * Cadastra um novo EPI e opcionalmente insere no histórico de preços
     */
    public function create(array $data, int $usuId): int {
        try {
            $this->db->beginTransaction();

            $tipoItem = $data['epi_tipo_item'] ?? 'EPI_COM_CA';

            $sql = "INSERT INTO EPIs (
                        epi_nome, epi_tipo_item, epi_ca, epi_vencimento_ca, epi_fabricante,
                        epi_validade_uso_dias, epi_status, epi_valor, epi_origem_preco, epi_localizacao,
                        epi_vida_util, epi_vida_util_unidade, epi_vida_util_tipo, epi_vida_util_alerta, epi_vida_util_obs,
                        epi_numero_lote, epi_modelo, epi_identificacao, epi_ref_fornecedor, epi_exige_tamanho
                    ) VALUES (
                        :nome, :tipo_item, :ca, :vencimento_ca, :fabricante,
                        :validade_uso, :status, :valor, :origem_preco, :localizacao,
                        :vida_util, :vida_util_unidade, :vida_util_tipo, :vida_util_alerta, :vida_util_obs,
                        :numero_lote, :modelo, :identificacao, :ref_fornecedor, :exige_tamanho
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nome'             => $data['epi_nome'],
                ':tipo_item'        => $tipoItem,
                ':ca'               => ($tipoItem === 'EPI_COM_CA') ? ($data['epi_ca'] ?? null) : null,
                ':vencimento_ca'    => ($tipoItem === 'EPI_COM_CA') ? ($data['epi_vencimento_ca'] ?? null) : null,
                ':fabricante'       => $data['epi_fabricante'],
                ':validade_uso'     => (int)($data['epi_validade_uso_dias'] ?? 0),
                ':status'           => $data['epi_status'] ?? 'ATIVO',
                ':valor'            => $data['epi_valor'] ?? 0.00,
                ':origem_preco'     => $data['epi_origem_preco'] ?? 'COMPRA_DIRETA',
                ':localizacao'      => $data['epi_localizacao'] ?? null,
                ':vida_util'        => isset($data['epi_vida_util']) ? (int)$data['epi_vida_util'] : null,
                ':vida_util_unidade'=> $data['epi_vida_util_unidade'] ?? null,
                ':vida_util_tipo'   => $data['epi_vida_util_tipo'] ?? 'CONTROLADO',
                ':vida_util_alerta' => isset($data['epi_vida_util_alerta']) ? (int)$data['epi_vida_util_alerta'] : null,
                ':vida_util_obs'    => $data['epi_vida_util_obs'] ?? null,
                ':numero_lote'      => $data['epi_numero_lote'] ?? null,
                ':modelo'           => $data['epi_modelo'] ?? null,
                ':identificacao'    => $data['epi_identificacao'] ?? null,
                ':ref_fornecedor'   => $data['epi_ref_fornecedor'] ?? null,
                ':exige_tamanho'    => isset($data['epi_exige_tamanho']) ? (int)$data['epi_exige_tamanho'] : 0
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
                'epi_nome'            => 'nome',
                'epi_tipo_item'       => 'tipo_item',
                'epi_ca'              => 'ca',
                'epi_vencimento_ca'   => 'vencimento_ca',
                'epi_fabricante'      => 'fabricante',
                'epi_validade_uso_dias'=> 'validade_uso',
                'epi_status'          => 'status',
                'epi_valor'           => 'valor',
                'epi_origem_preco'    => 'origem_preco',
                'epi_localizacao'     => 'localizacao',
                'epi_vida_util'       => 'vida_util',
                'epi_vida_util_unidade'=> 'vida_util_unidade',
                'epi_vida_util_tipo'  => 'vida_util_tipo',
                'epi_vida_util_alerta'=> 'vida_util_alerta',
                'epi_vida_util_obs'   => 'vida_util_obs',
                'epi_numero_lote'     => 'numero_lote',
                'epi_modelo'          => 'modelo',
                'epi_identificacao'   => 'identificacao',
                'epi_ref_fornecedor'  => 'ref_fornecedor',
                'epi_exige_tamanho'   => 'exige_tamanho'
            ];

            // Se mudou para tipo sem CA (ITEM_SEGURANCA_SEM_CA, UNIFORME, OUTRO), forçar limpeza de C.A. no próprio payload data
            if (isset($data['epi_tipo_item']) && in_array($data['epi_tipo_item'], ['ITEM_SEGURANCA_SEM_CA', 'UNIFORME', 'OUTRO'], true)) {
                $data['epi_ca'] = null;
                $data['epi_vencimento_ca'] = null;
            }

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
     * Busca EPIs com C.A. vencido (apenas EPI_COM_CA)
     */
    public function findExpiredCa(): array {
        $sql = "SELECT * FROM EPIs 
                WHERE epi_tipo_item = 'EPI_COM_CA'
                  AND epi_vencimento_ca < CURDATE()
                  AND epi_status != 'INATIVO'";
        $stmt = $this->db->query($sql);
        $epis = $stmt->fetchAll();
        foreach ($epis as &$e) {
            if (isset($e['epi_exige_tamanho'])) {
                $e['epi_exige_tamanho'] = (bool)(int)$e['epi_exige_tamanho'];
            }
        }
        return $epis;
    }

    /**
     * Busca EPIs com C.A. próximo do vencimento (apenas EPI_COM_CA)
     */
    public function findNextExpirationCa(int $days = 30): array {
        $sql = "SELECT * FROM EPIs 
                WHERE epi_tipo_item = 'EPI_COM_CA'
                  AND epi_vencimento_ca >= CURDATE()
                  AND epi_vencimento_ca <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
                  AND epi_status != 'INATIVO'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':days' => $days]);
        $epis = $stmt->fetchAll();
        foreach ($epis as &$e) {
            if (isset($e['epi_exige_tamanho'])) {
                $e['epi_exige_tamanho'] = (bool)(int)$e['epi_exige_tamanho'];
            }
        }
        return $epis;
    }

    /**
     * Busca itens SEM_CA que não possuem nenhum dado de rastreabilidade
     */
    public function findSemRastreabilidade(): array {
        $sql = "SELECT * FROM EPIs 
                WHERE epi_tipo_item = 'ITEM_SEGURANCA_SEM_CA'
                  AND (epi_numero_lote IS NULL OR epi_numero_lote = '')
                  AND (epi_modelo IS NULL OR epi_modelo = '')
                  AND (epi_identificacao IS NULL OR epi_identificacao = '')
                  AND (epi_ref_fornecedor IS NULL OR epi_ref_fornecedor = '')
                  AND epi_status != 'INATIVO'";
        $stmt = $this->db->query($sql);
        $epis = $stmt->fetchAll();
        foreach ($epis as &$e) {
            if (isset($e['epi_exige_tamanho'])) {
                $e['epi_exige_tamanho'] = (bool)(int)$e['epi_exige_tamanho'];
            }
        }
        return $epis;
    }

    /**
     * Cadastra um novo EPI com ID específico
     */
    public function createWithId(int $id, array $data, int $usuId): int {
        try {
            $this->db->beginTransaction();

            $tipoItem = $data['epi_tipo_item'] ?? 'EPI_COM_CA';

            $sql = "INSERT INTO EPIs (
                        epi_id, epi_nome, epi_tipo_item, epi_ca, epi_vencimento_ca, epi_fabricante,
                        epi_validade_uso_dias, epi_status, epi_valor, epi_origem_preco, epi_localizacao,
                        epi_vida_util, epi_vida_util_unidade, epi_vida_util_tipo, epi_vida_util_alerta, epi_vida_util_obs,
                        epi_numero_lote, epi_modelo, epi_identificacao, epi_ref_fornecedor, epi_exige_tamanho
                    ) VALUES (
                        :id, :nome, :tipo_item, :ca, :vencimento_ca, :fabricante,
                        :validade_uso, :status, :valor, :origem_preco, :localizacao,
                        :vida_util, :vida_util_unidade, :vida_util_tipo, :vida_util_alerta, :vida_util_obs,
                        :numero_lote, :modelo, :identificacao, :ref_fornecedor, :exige_tamanho
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id'               => $id,
                ':nome'             => $data['epi_nome'],
                ':tipo_item'        => $tipoItem,
                ':ca'               => ($tipoItem === 'EPI_COM_CA') ? ($data['epi_ca'] ?? null) : null,
                ':vencimento_ca'    => ($tipoItem === 'EPI_COM_CA') ? ($data['epi_vencimento_ca'] ?? null) : null,
                ':fabricante'       => $data['epi_fabricante'],
                ':validade_uso'     => (int)($data['epi_validade_uso_dias'] ?? 0),
                ':status'           => $data['epi_status'] ?? 'ATIVO',
                ':valor'            => $data['epi_valor'] ?? 0.00,
                ':origem_preco'     => $data['epi_origem_preco'] ?? 'COMPRA_DIRETA',
                ':localizacao'      => $data['epi_localizacao'] ?? null,
                ':vida_util'        => isset($data['epi_vida_util']) ? (int)$data['epi_vida_util'] : null,
                ':vida_util_unidade'=> $data['epi_vida_util_unidade'] ?? null,
                ':vida_util_tipo'   => $data['epi_vida_util_tipo'] ?? 'CONTROLADO',
                ':vida_util_alerta' => isset($data['epi_vida_util_alerta']) ? (int)$data['epi_vida_util_alerta'] : null,
                ':vida_util_obs'    => $data['epi_vida_util_obs'] ?? null,
                ':numero_lote'      => $data['epi_numero_lote'] ?? null,
                ':modelo'           => $data['epi_modelo'] ?? null,
                ':identificacao'    => $data['epi_identificacao'] ?? null,
                ':ref_fornecedor'   => $data['epi_ref_fornecedor'] ?? null,
                ':exige_tamanho'    => isset($data['epi_exige_tamanho']) ? (int)$data['epi_exige_tamanho'] : 0
            ]);

            // Grava o histórico inicial de preços
            $this->addPriceHistory([
                'epi_id' => $id,
                'usu_id' => $usuId,
                'hist_valor' => $data['epi_valor'] ?? 0.00,
                'hist_origem' => $data['epi_origem_preco'] ?? 'COMPRA_DIRETA',
                'hist_nota_fiscal' => $data['hist_nota_fiscal'] ?? null,
                'hist_fornecedor' => $data['hist_fornecedor'] ?? null
            ]);

            $this->db->commit();
            return $id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
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
