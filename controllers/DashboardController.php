<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Config\Database;
use PDO;
use Exception;

class DashboardController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * GET /dashboard/resumo
     * Retorna contadores gerais do sistema
     */
    public function resumo(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);

        try {
            $sql = "SELECT 
                        (SELECT COUNT(*) FROM Funcionarios WHERE fun_situacao = 'ATIVO') as funcionarios_ativos,
                        (SELECT COUNT(*) FROM EPIs WHERE epi_status = 'ATIVO') as epis_ativos,
                        (SELECT COUNT(*) FROM Entrega_EPIs WHERE entr_status = 'FINALIZADA') as entregas_realizadas,
                        (SELECT COUNT(*) FROM Assinatura_Eletronica WHERE ass_status = 'ATIVO') as assinaturas_ativas";
            
            $stmt = $this->db->query($sql);
            $dados = $stmt->fetch();

            Response::json(true, "Resumo estatístico do dashboard.", $dados);
        } catch (Exception $e) {
            Response::json(false, "Erro ao carregar resumo: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /dashboard/custos
     * Retorna dados financeiros simplificados das entregas
     */
    public function custos(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'GESTOR']);

        try {
            $sql = "SELECT 
                        COALESCE(SUM(i.item_quantidade * ep.epi_valor), 0) as custo_total_acumulado,
                        COALESCE(AVG(i.item_quantidade * ep.epi_valor), 0) as custo_medio_por_item,
                        (SELECT COUNT(*) FROM Historico_Preco_EPI) as total_atualizacoes_preco
                    FROM Itens_Entrega i
                    JOIN Entrega_EPIs e ON i.entr_id = e.entr_id
                    JOIN EPIs ep ON i.epi_id = ep.epi_id
                    WHERE e.entr_status = 'FINALIZADA'";
            
            $stmt = $this->db->query($sql);
            $dados = $stmt->fetch();

            // Formata os valores decimais
            $dados['custo_total_acumulado'] = (float)$dados['custo_total_acumulado'];
            $dados['custo_medio_por_item'] = (float)$dados['custo_medio_por_item'];

            Response::json(true, "Dados de custos consolidados.", $dados);
        } catch (Exception $e) {
            Response::json(false, "Erro ao calcular custos: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /dashboard/top-epis
     * Retorna os 5 EPIs mais entregues
     */
    public function topEpis(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);

        try {
            $sql = "SELECT 
                        ep.epi_id,
                        ep.epi_nome,
                        ep.epi_ca,
                        SUM(i.item_quantidade) as total_entregue
                    FROM Itens_Entrega i
                    JOIN EPIs ep ON i.epi_id = ep.epi_id
                    JOIN Entrega_EPIs e ON i.entr_id = e.entr_id
                    WHERE e.entr_status = 'FINALIZADA'
                    GROUP BY ep.epi_id, ep.epi_nome, ep.epi_ca
                    ORDER BY total_entregue DESC
                    LIMIT 5";
            
            $stmt = $this->db->query($sql);
            $dados = $stmt->fetchAll();

            Response::json(true, "Top 5 EPIs mais entregues.", $dados);
        } catch (Exception $e) {
            Response::json(false, "Erro ao processar ranking de EPIs: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /dashboard/pendencias
     * Retorna contadores de possíveis gargalos ou atenções operacionais (SST)
     */
    public function pendencias(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);

        try {
            // Conta assinaturas bloqueadas
            $sqlBloqueios = "SELECT COUNT(*) FROM Assinatura_Eletronica WHERE ass_status = 'BLOQUEADO'";
            $stmtBloqueios = $this->db->query($sqlBloqueios);
            $bloqueios = (int)$stmtBloqueios->fetchColumn();

            // Conta C.A. já vencidos
            $sqlVencidos = "SELECT COUNT(*) FROM EPIs WHERE epi_vencimento_ca < CURDATE() AND epi_status != 'INATIVO'";
            $stmtVencidos = $this->db->query($sqlVencidos);
            $vencidos = (int)$stmtVencidos->fetchColumn();

            // Conta C.A. a vencer em 30 dias
            $sqlAVencer = "SELECT COUNT(*) FROM EPIs 
                           WHERE epi_vencimento_ca >= CURDATE() 
                             AND epi_vencimento_ca <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                             AND epi_status != 'INATIVO'";
            $stmtAVencer = $this->db->query($sqlAVencer);
            $aVencer = (int)$stmtAVencer->fetchColumn();

            // Itens entregues e pendentes de devolução (status 'ENTREGUE')
            $sqlItensPendentes = "SELECT COUNT(*) FROM Itens_Entrega WHERE item_status = 'ENTREGUE'";
            $stmtItensPendentes = $this->db->query($sqlItensPendentes);
            $itensPendentes = (int)$stmtItensPendentes->fetchColumn();

            Response::json(true, "Pendências operacionais carregadas.", [
                'assinaturas_bloqueadas' => $bloqueios,
                'ca_vencidos' => $vencidos,
                'ca_a_vencer_30_dias' => $aVencer,
                'epis_pendentes_devolucao' => $itensPendentes
            ]);
        } catch (Exception $e) {
            Response::json(false, "Erro ao carregar pendências: " . $e->getMessage(), null, 500);
        }
    }
}
