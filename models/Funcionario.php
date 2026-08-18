<?php
declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use Exception;
use Security\CryptoService;

class Funcionario {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Auxiliar privado para descriptografar os campos CPF e eSocial após a leitura do banco
     */
    private function decryptFields(?array $row): ?array {
        if (!$row) {
            return null;
        }

        // Descriptografa o CPF
        if (!empty($row['fun_cpf_enc']) && !empty($row['fun_cpf_iv']) && !empty($row['fun_cpf_tag'])) {
            try {
                $row['fun_cpf'] = CryptoService::decrypt(
                    $row['fun_cpf_enc'],
                    $row['fun_cpf_iv'],
                    $row['fun_cpf_tag']
                );
            } catch (Exception $e) {
                // Mantém o valor bruto se a criptografia falhar (retrocompatibilidade)
                $row['fun_cpf'] = $row['fun_cpf'] ?? '';
            }
        } else {
            $row['fun_cpf'] = $row['fun_cpf'] ?? '';
        }

        // Descriptografa o eSocial
        if (!empty($row['fun_esocial_enc']) && !empty($row['fun_esocial_iv']) && !empty($row['fun_esocial_tag'])) {
            try {
                $row['fun_esocial'] = CryptoService::decrypt(
                    $row['fun_esocial_enc'],
                    $row['fun_esocial_iv'],
                    $row['fun_esocial_tag']
                );
            } catch (Exception $e) {
                $row['fun_esocial'] = $row['fun_esocial'] ?? '';
            }
        } else {
            $row['fun_esocial'] = $row['fun_esocial'] ?? '';
        }

        return $row;
    }

    /**
     * Lista todos os funcionários. A responsabilidade por mascaramento agora fica 100% no PHP.
     */
    public function findAll(): array {
        $sql = "SELECT f.*, a.ass_status AS assinatura_status 
                FROM funcionarios f
                LEFT JOIN assinatura_eletronica a ON f.fun_id = a.fun_id
                ORDER BY f.fun_nome ASC";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetchAll();

        return array_map([$this, 'decryptFields'], $result);
    }

    /**
     * Busca funcionário pelo ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT f.*, a.ass_status AS assinatura_status 
                FROM funcionarios f
                LEFT JOIN assinatura_eletronica a ON f.fun_id = a.fun_id
                WHERE f.fun_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $funcionario = $stmt->fetch();

        return $funcionario ? $this->decryptFields($funcionario) : null;
    }

    /**
     * Busca funcionário pelo QR Code
     */
    public function findByQrCode(string $qrCode): ?array {
        $sql = "SELECT f.*, a.ass_status AS assinatura_status 
                FROM funcionarios f
                LEFT JOIN assinatura_eletronica a ON f.fun_id = a.fun_id
                WHERE f.fun_qrcode = :qrcode LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':qrcode' => $qrCode]);
        $funcionario = $stmt->fetch();

        return $funcionario ? $this->decryptFields($funcionario) : null;
    }

    /**
     * Busca funcionário pelo CPF usando busca exata otimizada pelo lookup index HMAC-SHA-256
     */
    public function findByCpf(string $cpf): ?array {
        $lookup = CryptoService::generateLookup($cpf);

        $sql = "SELECT * FROM funcionarios WHERE fun_cpf_lookup = :lookup LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':lookup' => $lookup]);
        $funcionario = $stmt->fetch();

        // Se não encontrar por lookup, tenta busca direta em texto puro (retrocompatibilidade temporária)
        if (!$funcionario) {
            $sql = "SELECT * FROM funcionarios WHERE fun_cpf = :cpf LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':cpf' => $cpf]);
            $funcionario = $stmt->fetch();
        }

        return $funcionario ? $this->decryptFields($funcionario) : null;
    }

    /**
     * Cria um novo funcionário na tabela física aplicando a criptografia AES e lookup HMAC
     */
    public function create(array $data): int {
        // Encripta CPF
        $cpfClean = preg_replace('/\D/', '', $data['fun_cpf']);
        $cpfEnc = CryptoService::encrypt($cpfClean);
        $cpfLookup = CryptoService::generateLookup($cpfClean);

        // Encripta eSocial
        $esocialClean = preg_replace('/\s+/', '', $data['fun_esocial']);
        $esocialEnc = CryptoService::encrypt($esocialClean);

        $sql = "INSERT INTO funcionarios (
                    fun_nome, fun_cpf, fun_cpf_enc, fun_cpf_iv, fun_cpf_tag, fun_cpf_lookup,
                    fun_esocial, fun_esocial_enc, fun_esocial_iv, fun_esocial_tag,
                    fun_departamento, fun_cargo, fun_dataadmissao, fun_situacao, fun_qrcode
                ) VALUES (
                    :nome, :cpf, :cpf_enc, :cpf_iv, :cpf_tag, :cpf_lookup,
                    :esocial, :esocial_enc, :esocial_iv, :esocial_tag,
                    :departamento, :cargo, :dataadmissao, :situacao, :qrcode
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nome' => $data['fun_nome'],
            ':cpf' => $data['fun_cpf'], // Mantém compatibilidade temporária de coluna
            ':cpf_enc' => $cpfEnc['ciphertext'],
            ':cpf_iv' => $cpfEnc['iv'],
            ':cpf_tag' => $cpfEnc['tag'],
            ':cpf_lookup' => $cpfLookup,
            ':esocial' => $data['fun_esocial'], // Mantém compatibilidade temporária de coluna
            ':esocial_enc' => $esocialEnc['ciphertext'],
            ':esocial_iv' => $esocialEnc['iv'],
            ':esocial_tag' => $esocialEnc['tag'],
            ':departamento' => $data['fun_departamento'],
            ':cargo' => $data['fun_cargo'],
            ':dataadmissao' => $data['fun_dataadmissao'],
            ':situacao' => $data['fun_situacao'] ?? 'ATIVO',
            ':qrcode' => $data['fun_qrcode']
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Atualiza dados de um funcionário encriptando novas entradas
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        $updatableFields = [
            'fun_nome' => 'nome',
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

        // Criptografia de CPF se informado
        if (isset($data['fun_cpf'])) {
            $cpfClean = preg_replace('/\D/', '', $data['fun_cpf']);
            $cpfEnc = CryptoService::encrypt($cpfClean);
            $cpfLookup = CryptoService::generateLookup($cpfClean);

            $fields[] = "fun_cpf = :cpf";
            $fields[] = "fun_cpf_enc = :cpf_enc";
            $fields[] = "fun_cpf_iv = :cpf_iv";
            $fields[] = "fun_cpf_tag = :cpf_tag";
            $fields[] = "fun_cpf_lookup = :cpf_lookup";

            $params[':cpf'] = $data['fun_cpf'];
            $params[':cpf_enc'] = $cpfEnc['ciphertext'];
            $params[':cpf_iv'] = $cpfEnc['iv'];
            $params[':cpf_tag'] = $cpfEnc['tag'];
            $params[':cpf_lookup'] = $cpfLookup;
        }

        // Criptografia de eSocial se informado
        if (isset($data['fun_esocial'])) {
            $esocialClean = preg_replace('/\s+/', '', $data['fun_esocial']);
            $esocialEnc = CryptoService::encrypt($esocialClean);

            $fields[] = "fun_esocial = :esocial";
            $fields[] = "fun_esocial_enc = :esocial_enc";
            $fields[] = "fun_esocial_iv = :esocial_iv";
            $fields[] = "fun_esocial_tag = :esocial_tag";

            $params[':esocial'] = $data['fun_esocial'];
            $params[':esocial_enc'] = $esocialEnc['ciphertext'];
            $params[':esocial_iv'] = $esocialEnc['iv'];
            $params[':esocial_tag'] = $esocialEnc['tag'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE funcionarios SET " . implode(", ", $fields) . " WHERE fun_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Efetua exclusão lógica
     */
    public function delete(int $id): bool {
        $sql = "UPDATE funcionarios SET fun_situacao = 'INATIVO' WHERE fun_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Cria um novo funcionário com ID específico (para migrações ou seeds)
     */
    public function createWithId(int $id, array $data): int {
        // Encripta CPF
        $cpfClean = preg_replace('/\D/', '', $data['fun_cpf']);
        $cpfEnc = CryptoService::encrypt($cpfClean);
        $cpfLookup = CryptoService::generateLookup($cpfClean);

        // Encripta eSocial
        $esocialClean = preg_replace('/\s+/', '', $data['fun_esocial']);
        $esocialEnc = CryptoService::encrypt($esocialClean);

        $sql = "INSERT INTO funcionarios (
                    fun_id, fun_nome, fun_cpf, fun_cpf_enc, fun_cpf_iv, fun_cpf_tag, fun_cpf_lookup,
                    fun_esocial, fun_esocial_enc, fun_esocial_iv, fun_esocial_tag,
                    fun_departamento, fun_cargo, fun_dataadmissao, fun_situacao, fun_qrcode
                ) VALUES (
                    :id, :nome, :cpf, :cpf_enc, :cpf_iv, :cpf_tag, :cpf_lookup,
                    :esocial, :esocial_enc, :esocial_iv, :esocial_tag,
                    :departamento, :cargo, :dataadmissao, :situacao, :qrcode
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':nome' => $data['fun_nome'],
            ':cpf' => $data['fun_cpf'],
            ':cpf_enc' => $cpfEnc['ciphertext'],
            ':cpf_iv' => $cpfEnc['iv'],
            ':cpf_tag' => $cpfEnc['tag'],
            ':cpf_lookup' => $cpfLookup,
            ':esocial' => $data['fun_esocial'],
            ':esocial_enc' => $esocialEnc['ciphertext'],
            ':esocial_iv' => $esocialEnc['iv'],
            ':esocial_tag' => $esocialEnc['tag'],
            ':departamento' => $data['fun_departamento'],
            ':cargo' => $data['fun_cargo'],
            ':dataadmissao' => $data['fun_dataadmissao'],
            ':situacao' => $data['fun_situacao'] ?? 'ATIVO',
            ':qrcode' => $data['fun_qrcode']
        ]);

        return $id;
    }

    /**
     * Marca o funcionário como ativo após cadastro de assinatura eletrônica
     */
    public function markAsActiveAfterSignature(int $funId): bool {
        $sql = "UPDATE funcionarios SET fun_situacao = 'ATIVO' WHERE fun_id = :fun_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':fun_id' => $funId]);
    }
}
