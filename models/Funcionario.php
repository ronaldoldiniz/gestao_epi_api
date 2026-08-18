<?php
declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use Exception;

class Funcionario {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Retorna a fonte de dados (tabela física ou view) baseada na necessidade de mascarar dados
     */
    private function getSource(bool $maskData): string {
        return $maskData ? 'vw_funcionarios_mascarado' : 'Funcionarios';
    }

    /**
     * Lista todos os funcionários (ativos ou não) conforme o nível de privilégio
     */
    public function findAll(bool $maskData): array {
        $source = $this->getSource($maskData);
        $sql = "SELECT * FROM {$source} ORDER BY fun_nome ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Busca funcionário pelo ID conforme o nível de privilégio
     */
    public function findById(int $id, bool $maskData): ?array {
        $source = $this->getSource($maskData);
        $sql = "SELECT * FROM {$source} WHERE fun_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $funcionario = $stmt->fetch();
        return $funcionario ?: null;
    }

    /**
     * Busca funcionário pelo QR Code conforme o nível de privilégio
     */
    public function findByQrCode(string $qrCode, bool $maskData): ?array {
        $source = $this->getSource($maskData);
        $sql = "SELECT * FROM {$source} WHERE fun_qrcode = :qrcode LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':qrcode' => $qrCode]);
        $funcionario = $stmt->fetch();
        return $funcionario ?: null;
    }

    /**
     * Cria um novo funcionário na tabela física
     */
    public function create(array $data): int {
        $sql = "INSERT INTO Funcionarios (fun_nome, fun_cpf, fun_esocial, fun_departamento, fun_cargo, fun_dataadmissao, fun_situacao, fun_qrcode)
                VALUES (:nome, :cpf, :esocial, :departamento, :cargo, :dataadmissao, :situacao, :qrcode)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nome' => $data['fun_nome'],
            ':cpf' => $data['fun_cpf'],
            ':esocial' => $data['fun_esocial'],
            ':departamento' => $data['fun_departamento'],
            ':cargo' => $data['fun_cargo'],
            ':dataadmissao' => $data['fun_dataadmissao'],
            ':situacao' => $data['fun_situacao'] ?? 'ATIVO',
            ':qrcode' => $data['fun_qrcode']
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Atualiza dados de um funcionário na tabela física
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        $updatableFields = [
            'fun_nome' => 'nome',
            'fun_cpf' => 'cpf',
            'fun_esocial' => 'esocial',
            'fun_departamento' => 'departamento',
            'fun_cargo' => 'cargo',
            'fun_dataadmissao' => 'dataadmissao',
            'fun_situacao' => 'situacao',
            'fun_qrcode' => 'qrcode'
        ];

        foreach ($updatableFields as $dbField => $paramKey) {
            if (isset($data[$dbField])) {
                $fields[] = "{$dbField} = :{$paramKey}";
                $params[":{$paramKey}"] = $data[$dbField];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE Funcionarios SET " . implode(", ", $fields) . " WHERE fun_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Efetua exclusão lógica alterando a situação do funcionário para INATIVO
     */
    public function delete(int $id): bool {
        $sql = "UPDATE Funcionarios SET fun_situacao = 'INATIVO' WHERE fun_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Marca o funcionário como ativo após o cadastro/atualização da assinatura eletrônica
     */
    public function markAsActiveAfterSignature(int $funId): bool {
        $sql = "UPDATE Funcionarios SET fun_situacao = 'ATIVO' WHERE fun_id = :fun_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':fun_id' => $funId]);
    }
}
