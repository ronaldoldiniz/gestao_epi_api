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

    public function aceitarTermos(int $id, ?int $dataAceite = null): bool {
        // 1. Busca a definicao mestre ativa dos Termos e Politicas (LGPD)
        $sqlTermo = "SELECT termo_id, termo_codigo, termo_versao, termo_titulo, termo_texto_completo 
                     FROM termos_responsabilidade 
                     WHERE termo_codigo = 'TERMOS_POLITICAS_LGPD' AND termo_usu_id IS NULL AND termo_status = 'ATIVO' 
                     LIMIT 1";
        $stmtTermo = $this->db->query($sqlTermo);
        $termo = $stmtTermo->fetch();

        if (!$termo) {
            throw new Exception("Termos e Politicas (LGPD) mestre ativo nao encontrado no sistema.");
        }

        $termoId = (int)$termo['termo_id'];
        $termoCodigo = $termo['termo_codigo'];
        $termoVersao = $termo['termo_versao'];
        $termoTitulo = $termo['termo_titulo'];
        $textoCompleto = $termo['termo_texto_completo'];
        $hashTermo = hash('sha256', $textoCompleto);

        // Converte o timestamp (que o Android envia em milissegundos) para MySQL DATETIME
        $timestampSec = $dataAceite ? (int)($dataAceite / 1000) : time();
        $dataHoraAceite = date('Y-m-d H:i:s', $timestampSec);

        // 2. Insere um novo registro de termos_responsabilidade representando o aceite e o snapshot
        $sql = "INSERT INTO termos_responsabilidade (
                    termo_codigo, termo_versao, termo_titulo, termo_texto_completo, 
                    termo_data_inicio_vigencia, termo_status, usu_cadastro_id, termo_data_hora_cadastro,
                    termo_usu_id, termo_texto_snapshot, termo_data_hora_aceite, termo_metodo_aceite, termo_hash_termo
                ) VALUES (
                    :termo_codigo, :termo_versao, :termo_titulo, :termo_texto_completo,
                    NOW(), 'ATIVO', :usu_cadastro_id, NOW(),
                    :termo_usu_id, :termo_texto_snapshot, :termo_data_hora_aceite, :termo_metodo_aceite, :termo_hash_termo
                )";
                
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':termo_codigo' => $termoCodigo,
            ':termo_versao' => $termoVersao,
            ':termo_titulo' => $termoTitulo,
            ':termo_texto_completo' => $textoCompleto,
            ':usu_cadastro_id' => $id,
            ':termo_usu_id' => $id,
            ':termo_texto_snapshot' => $textoCompleto,
            ':termo_data_hora_aceite' => $dataHoraAceite,
            ':termo_metodo_aceite' => 'INTERFACE_WEB_MOBI',
            ':termo_hash_termo' => $hashTermo
        ]);
    }

    /**
     * Busca usuário pelo ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT u.usu_id, u.usu_login, u.usu_perfil, u.usu_status, u.usu_data_cadastro, 
                       u.usu_tentativas_falha, u.usu_data_bloqueio, u.usu_motivo_bloqueio, u.usu_exige_troca_senha,
                       (SELECT MAX(log_datahora) FROM log_auditoria WHERE usu_id = u.usu_id AND log_acao = 'LOGIN') as usu_ultimo_login,
                       t.termo_id, t.termo_data_hora_aceite
                FROM usuarios u 
                LEFT JOIN termos_responsabilidade t ON t.termo_usu_id = u.usu_id AND t.termo_codigo = 'TERMOS_POLITICAS_LGPD' AND t.termo_versao = '1.0' AND t.termo_data_hora_aceite IS NOT NULL
                WHERE u.usu_id = :id 
                ORDER BY t.termo_data_hora_aceite DESC 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $user['usu_aceite_termos'] = $user['termo_id'] !== null ? 1 : 0;
            $user['usu_data_aceite_termos'] = $user['termo_data_hora_aceite'] ? strtotime($user['termo_data_hora_aceite']) * 1000 : null;
        }
        
        return $user ?: null;
    }

    /**
     * Lista todos os usuários
     */
    public function findAll(): array {
        $sql = "SELECT u.usu_id, u.usu_login, u.usu_perfil, u.usu_status, u.usu_data_cadastro, 
                       u.usu_tentativas_falha, u.usu_data_bloqueio, u.usu_motivo_bloqueio, u.usu_exige_troca_senha,
                       (SELECT MAX(log_datahora) FROM log_auditoria WHERE usu_id = u.usu_id AND log_acao = 'LOGIN') as usu_ultimo_login,
                       t.termo_id, t.termo_data_hora_aceite
                FROM usuarios u
                LEFT JOIN (
                    SELECT t1.termo_usu_id, t1.termo_id, t1.termo_data_hora_aceite
                    FROM termos_responsabilidade t1
                    INNER JOIN (
                        SELECT termo_usu_id, MAX(termo_data_hora_aceite) as max_date
                        FROM termos_responsabilidade
                        WHERE termo_codigo = 'TERMOS_POLITICAS_LGPD' AND termo_versao = '1.0' AND termo_usu_id IS NOT NULL
                        GROUP BY termo_usu_id
                    ) t2 ON t1.termo_usu_id = t2.termo_usu_id AND t1.termo_data_hora_aceite = t2.max_date
                ) t ON t.termo_usu_id = u.usu_id
                ORDER BY u.usu_login ASC";
        $stmt = $this->db->query($sql);
        $users = $stmt->fetchAll();
        
        foreach ($users as &$user) {
            $user['usu_aceite_termos'] = $user['termo_id'] !== null ? 1 : 0;
            $user['usu_data_aceite_termos'] = $user['termo_data_hora_aceite'] ? strtotime($user['termo_data_hora_aceite']) * 1000 : null;
        }
        
        return $users;
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
