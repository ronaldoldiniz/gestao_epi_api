<?php
declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;

class LogAuditoria {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Busca logs de auditoria com base nos filtros informados
     */
    public function findFiltered(array $filters): array {
        $sql = "SELECT l.*, u.usu_login, u.usu_perfil 
                FROM log_auditoria l
                LEFT JOIN usuarios u ON l.usu_id = u.usu_id
                LEFT JOIN funcionarios f ON l.fun_id = f.fun_id
                WHERE 1=1";
        
        $params = [];

        if (!empty($filters['usuario'])) {
            $sql .= " AND u.usu_login LIKE :usuario";
            $params[':usuario'] = '%' . $filters['usuario'] . '%';
        }

        if (!empty($filters['funcionario'])) {
            $funcionarioFiltro = $filters['funcionario'];
            if (is_numeric($funcionarioFiltro)) {
                $sql .= " AND (f.fun_id = :funcionario_id OR f.fun_nome LIKE :funcionario_nome)";
                $params[':funcionario_id'] = (int)$funcionarioFiltro;
                $params[':funcionario_nome'] = '%' . $funcionarioFiltro . '%';
            } else {
                $sql .= " AND f.fun_nome LIKE :funcionario_nome";
                $params[':funcionario_nome'] = '%' . $funcionarioFiltro . '%';
            }
        }

        if (!empty($filters['data_inicio'])) {
            $sql .= " AND l.log_datahora >= :data_inicio";
            $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
        }

        if (!empty($filters['data_fim'])) {
            $sql .= " AND l.log_datahora <= :data_fim";
            $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
        }

        if (!empty($filters['acao'])) {
            $sql .= " AND l.log_acao = :acao";
            $params[':acao'] = $filters['acao'];
        }

        if (!empty($filters['entidade'])) {
            $sql .= " AND l.log_tabela = :entidade";
            $params[':entidade'] = $filters['entidade'];
        }

        if (!empty($filters['palavra_chave'])) {
            $sql .= " AND (l.log_detalhes LIKE :palavra_chave_detalhes 
                           OR l.log_acao LIKE :palavra_chave_acao 
                           OR l.log_tabela LIKE :palavra_chave_tabela 
                           OR u.usu_login LIKE :palavra_chave_usuario 
                           OR l.log_registro_id = :palavra_chave_exact)";
            $val = '%' . $filters['palavra_chave'] . '%';
            $params[':palavra_chave_detalhes'] = $val;
            $params[':palavra_chave_acao'] = $val;
            $params[':palavra_chave_tabela'] = $val;
            $params[':palavra_chave_usuario'] = $val;
            $params[':palavra_chave_exact'] = is_numeric($filters['palavra_chave']) ? (int)$filters['palavra_chave'] : -1;
        }

        $sql .= " ORDER BY l.log_datahora DESC LIMIT 5000";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um log de auditoria específico pelo ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT l.*, u.usu_login, u.usu_perfil 
                FROM log_auditoria l
                LEFT JOIN usuarios u ON l.usu_id = u.usu_id
                WHERE l.log_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
