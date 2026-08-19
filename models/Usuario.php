<?php
declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use Exception;

class Usuario {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Busca usuário pelo login
     */
    public function findByLogin(string $login): ?array {
        $sql = "SELECT * FROM usuarios WHERE usu_login = :login LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':login' => $login]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Registra o aceite dos Termos de Uso
     */
    public function aceitarTermos(int $id, ?int $dataAceite = null): bool {
        $timestamp = $dataAceite ?? round(microtime(true) * 1000);
        $sql = "UPDATE usuarios SET usu_aceite_termos = 1, usu_data_aceite_termos = :data_aceite WHERE usu_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':data_aceite' => $timestamp
        ]);
    }

    /**
     * Busca usuário pelo ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT u.usu_id, u.usu_login, u.usu_perfil, u.usu_status, u.usu_data_cadastro, 
                       u.usu_tentativas_falha, u.usu_data_bloqueio, u.usu_motivo_bloqueio, u.usu_exige_troca_senha,
                       u.usu_aceite_termos, u.usu_data_aceite_termos,
                       (SELECT MAX(log_datahora) FROM log_auditoria WHERE usu_id = u.usu_id AND log_acao = 'LOGIN') as usu_ultimo_login
                FROM usuarios u WHERE u.usu_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Lista todos os usuários
     */
    public function findAll(): array {
        $sql = "SELECT u.usu_id, u.usu_login, u.usu_perfil, u.usu_status, u.usu_data_cadastro, 
                       u.usu_tentativas_falha, u.usu_data_bloqueio, u.usu_motivo_bloqueio, u.usu_exige_troca_senha,
                       u.usu_aceite_termos, u.usu_data_aceite_termos,
                       (SELECT MAX(log_datahora) FROM log_auditoria WHERE usu_id = u.usu_id AND log_acao = 'LOGIN') as usu_ultimo_login
                FROM usuarios u ORDER BY u.usu_login ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Cadastra um novo usuário gerando o hash de senha
     */
    public function create(array $data): int {
        $salt = \Security\PasswordService::generateSalt();
        $hash = \Security\PasswordService::hash($data['senha'], $salt);

        $sql = "INSERT INTO usuarios (usu_login, usu_senha_hash, usu_senha_salt, usu_perfil, usu_status, usu_data_cadastro, usu_tentativas_falha, usu_exige_troca_senha)
                VALUES (:login, :senha_hash, :senha_salt, :perfil, :status, NOW(), 0, :exige_troca_senha)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':login' => $data['usu_login'],
            ':senha_hash' => $hash,
            ':senha_salt' => $salt,
            ':perfil' => $data['usu_perfil'],
            ':status' => $data['usu_status'] ?? 'ATIVO',
            ':exige_troca_senha' => isset($data['usu_exige_troca_senha']) ? ((int)$data['usu_exige_troca_senha']) : 0
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Atualiza dados de um usuário
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['usu_login'])) {
            $fields[] = "usu_login = :login";
            $params[':login'] = $data['usu_login'];
        }
        if (isset($data['senha']) && $data['senha'] !== '') {
            $salt = \Security\PasswordService::generateSalt();
            $hash = \Security\PasswordService::hash($data['senha'], $salt);
            
            $fields[] = "usu_senha_hash = :senha_hash";
            $fields[] = "usu_senha_salt = :senha_salt";
            $params[':senha_hash'] = $hash;
            $params[':senha_salt'] = $salt;
        }
        if (isset($data['usu_perfil'])) {
            $fields[] = "usu_perfil = :perfil";
            $params[':perfil'] = $data['usu_perfil'];
        }
        if (isset($data['usu_status'])) {
            $fields[] = "usu_status = :status";
            $params[':status'] = $data['usu_status'];
        }
        if (isset($data['usu_exige_troca_senha'])) {
            $fields[] = "usu_exige_troca_senha = :exige_troca_senha";
            $params[':exige_troca_senha'] = $data['usu_exige_troca_senha'] ? 1 : 0;
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE usuarios SET " . implode(", ", $fields) . " WHERE usu_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Efetua exclusão lógica inativando o usuário
     */
    public function delete(int $id): bool {
        $sql = "UPDATE usuarios SET usu_status = 'INATIVO' WHERE usu_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Incrementa tentativas inválidas de login e bloqueia se exceder limite
     */
    public function incrementFailAttempts(string $login, int $maxAttempts): int {
        // Incrementa tentativa
        $sql = "UPDATE usuarios SET usu_tentativas_falha = usu_tentativas_falha + 1 WHERE usu_login = :login";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':login' => $login]);

        // Consulta estado atual
        $user = $this->findByLogin($login);
        if ($user && (int)$user['usu_tentativas_falha'] >= $maxAttempts) {
            $this->lockAccount($login, "Excesso de tentativas de login inválidas (" . $user['usu_tentativas_falha'] . ")");
        }

        return $user ? (int)$user['usu_tentativas_falha'] : 0;
    }

    /**
     * Zera tentativas falhas e remove bloqueio
     */
    public function resetFailAttempts(string $login): bool {
        $sql = "UPDATE usuarios 
                SET usu_tentativas_falha = 0, 
                    usu_data_bloqueio = NULL, 
                    usu_motivo_bloqueio = NULL 
                WHERE usu_login = :login";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':login' => $login]);
    }

    /**
     * Bloqueia temporariamente a conta do usuário
     */
    private function lockAccount(string $login, string $motivo): bool {
        $sql = "UPDATE usuarios 
                SET usu_data_bloqueio = NOW(), 
                    usu_motivo_bloqueio = :motivo 
                WHERE usu_login = :login";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':login' => $login,
            ':motivo' => $motivo
        ]);
    }

    /**
     * Verifica se o usuário está bloqueado
     */
    public function isLocked(array $user, int $lockoutTimeSeconds): bool {
        if ($user['usu_data_bloqueio'] === null) {
            return false;
        }

        $blockTime = strtotime($user['usu_data_bloqueio']);
        $diff = time() - $blockTime;

        if ($diff < $lockoutTimeSeconds) {
            return true;
        }

        // Se o tempo de bloqueio expirou, zera as tentativas automaticamente
        $this->resetFailAttempts($user['usu_login']);
        return false;
    }
}
