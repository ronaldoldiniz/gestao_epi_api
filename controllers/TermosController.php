<?php
declare(strict_types=1);

namespace Controllers;

use Config\Database;
use Core\Response;
use Core\Auth;
use PDO;
use Exception;

class TermosController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * GET /termos-politicas/vigente
     */
    public function getVigente(): void {
        Auth::requireAuth();

        $sql = "SELECT termo_id, termo_codigo, termo_versao, termo_titulo, termo_texto_completo, termo_data_inicio_vigencia 
                FROM termos_responsabilidade 
                WHERE termo_codigo = 'TERMOS_POLITICAS_LGPD' AND termo_versao = '1.0' AND termo_usu_id IS NULL AND termo_status = 'ATIVO' 
                LIMIT 1";
        
        $stmt = $this->db->query($sql);
        $termo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$termo) {
            Response::json(false, "Termos e Políticas (LGPD) vigente não configurado no sistema.", null, 404);
        }

        Response::json(true, "Termos e Políticas (LGPD) vigente recuperado.", [
            'termo_id' => (int)$termo['termo_id'],
            'termo_codigo' => $termo['termo_codigo'],
            'versao' => $termo['termo_versao'],
            'titulo' => $termo['termo_titulo'],
            'texto' => $termo['termo_texto_completo'],
            'data_inicio_vigencia' => $termo['termo_data_inicio_vigencia']
        ]);
    }

    /**
     * GET /termos-politicas/aceite/{usu_id}
     */
    public function getAceite(string $usu_id): void {
        Auth::requireAuth();

        $usuId = (int)$usu_id;

        $sql = "SELECT termo_id, termo_codigo, termo_versao, termo_data_hora_aceite, termo_metodo_aceite 
                FROM termos_responsabilidade 
                WHERE termo_codigo = 'TERMOS_POLITICAS_LGPD' AND termo_versao = '1.0' AND termo_usu_id = :usu_id AND termo_data_hora_aceite IS NOT NULL 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':usu_id' => $usuId]);
        $aceite = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($aceite) {
            Response::json(true, "Aceite localizado.", [
                'sucesso' => true,
                'aceito' => true,
                'usu_id' => $usuId,
                'termo_codigo' => $aceite['termo_codigo'],
                'versao' => $aceite['termo_versao'],
                'data_hora_aceite' => $aceite['termo_data_hora_aceite'],
                'metodo_aceite' => $aceite['termo_metodo_aceite']
            ]);
        } else {
            Response::json(true, "Aceite não localizado.", [
                'sucesso' => true,
                'aceito' => false,
                'usu_id' => $usuId,
                'termo_codigo' => 'TERMOS_POLITICAS_LGPD',
                'versao' => '1.0',
                'data_hora_aceite' => null,
                'metodo_aceite' => null
            ]);
        }
    }

    /**
     * POST /termos-politicas/aceite
     */
    public function registrarAceite(): void {
        $currentUser = Auth::requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input) || !isset($input['usu_id'])) {
            Response::json(false, "Requisição inválida. O ID do usuário é obrigatório.", null, 400);
        }

        $usuId = (int)$input['usu_id'];
        $termoCodigo = $input['termo_codigo'] ?? 'TERMOS_POLITICAS_LGPD';
        $termoVersao = $input['versao'] ?? '1.0';
        $metodoAceite = $input['metodo_aceite'] ?? 'CHECKBOX_APP';

        // 1. Impedir aceite duplicado
        $sqlCheck = "SELECT termo_id, termo_data_hora_aceite FROM termos_responsabilidade 
                     WHERE termo_codigo = :termo_codigo AND termo_versao = :termo_versao AND termo_usu_id = :usu_id AND termo_data_hora_aceite IS NOT NULL 
                     LIMIT 1";
        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->execute([
            ':termo_codigo' => $termoCodigo,
            ':termo_versao' => $termoVersao,
            ':usu_id' => $usuId
        ]);
        $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            Response::json(true, "Termos e Políticas (LGPD) já aceitos anteriormente.", [
                'sucesso' => true,
                'mensagem' => "Termos e Políticas (LGPD) já aceitos anteriormente.",
                'data_hora_aceite' => $existente['termo_data_hora_aceite']
            ]);
            return;
        }

        // 2. Busca o termo mestre vigente do sistema
        $sqlMestre = "SELECT termo_titulo, termo_texto_completo FROM termos_responsabilidade 
                      WHERE termo_codigo = :termo_codigo AND termo_versao = :termo_versao AND termo_usu_id IS NULL AND termo_status = 'ATIVO' 
                      LIMIT 1";
        $stmtMestre = $this->db->prepare($sqlMestre);
        $stmtMestre->execute([
            ':termo_codigo' => $termoCodigo,
            ':termo_versao' => $termoVersao
        ]);
        $mestre = $stmtMestre->fetch(PDO::FETCH_ASSOC);

        if (!$mestre) {
            Response::json(false, "Termos vigentes não localizados para registro do snapshot.", null, 404);
        }

        $termoTitulo = $mestre['termo_titulo'];
        $textoCompleto = $mestre['termo_texto_completo'];

        // Gera o hash SHA-256
        $hashBase = $termoCodigo . $termoVersao . $textoCompleto;
        $hashTermo = hash('sha256', $hashBase);

        $dataHoraAceite = date('Y-m-d H:i:s');

        // 3. Insere o aceite do usuário
        $sqlInsert = "INSERT INTO termos_responsabilidade (
                        termo_codigo, termo_versao, termo_titulo, termo_texto_completo,
                        termo_data_inicio_vigencia, termo_status, usu_cadastro_id, termo_data_hora_cadastro,
                        termo_usu_id, termo_texto_snapshot, termo_data_hora_aceite, termo_metodo_aceite, termo_hash_termo
                      ) VALUES (
                        :termo_codigo, :termo_versao, :termo_titulo, :termo_texto_completo,
                        NOW(), 'ATIVO', :currentUser_id, NOW(),
                        :usu_id, :texto_snapshot, :data_hora_aceite, :metodo_aceite, :hash_termo
                      )";
        
        $stmtInsert = $this->db->prepare($sqlInsert);
        $success = $stmtInsert->execute([
            ':termo_codigo' => $termoCodigo,
            ':termo_versao' => $termoVersao,
            ':termo_titulo' => $termoTitulo,
            ':termo_texto_completo' => $textoCompleto,
            ':currentUser_id' => (int)$currentUser['usu_id'],
            ':usu_id' => $usuId,
            ':texto_snapshot' => $textoCompleto,
            ':data_hora_aceite' => $dataHoraAceite,
            ':metodo_aceite' => $metodoAceite,
            ':hash_termo' => $hashTermo
        ]);

        if ($success) {
            $termoId = (int)$this->db->lastInsertId();

            // Grava Log de Auditoria
            \Core\Audit::log(
                'ACEITE_TERMOS_POLITICAS',
                'termos_responsabilidade',
                $termoId,
                "Usuário aceitou os Termos e Políticas LGPD versão 1.0.",
                $currentUser['usu_id']
            );

            Response::json(true, "Termos e Políticas (LGPD) aceitos com sucesso.", [
                'sucesso' => true,
                'mensagem' => "Termos e Políticas (LGPD) aceitos com sucesso.",
                'data_hora_aceite' => $dataHoraAceite
            ]);
        } else {
            Response::json(false, "Falha ao registrar aceite no banco de dados.", null, 500);
        }
    }
}
