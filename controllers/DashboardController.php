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
                        COALESCE(SUM(i.item_quantidade * COALESCE(i.item_epi_valor_snapshot, ep.epi_valor)), 0) as custo_total_acumulado,
                        COALESCE(AVG(i.item_quantidade * COALESCE(i.item_epi_valor_snapshot, ep.epi_valor)), 0) as custo_medio_por_item,
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

    /**
     * GET /dashboard
     * Retorna TODOS os dados consolidados do Dashboard em uma única resposta.
     * Garante que todos os dispositivos (tablets, smartphones, etc.) recebam exatamente
     * os mesmos dados calculados no servidor central.
     */
    public function index(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);

        try {
            $today = date('Y-m-d');
            $todayStart = $today . ' 00:00:00';
            $sevenDaysAhead = date('Y-m-d', strtotime('+7 days'));
            $firstDayOfMonth = date('Y-m-01') . ' 00:00:00';

            // 1. Alertas de C.A.
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM EPIs 
                 WHERE epi_status != 'INATIVO' 
                 AND epi_tipo_item = 'EPI_COM_CA'
                 AND (epi_status = 'VENCIDO' OR (epi_vencimento_ca IS NOT NULL AND epi_vencimento_ca < :now))"
            );
            $stmt->execute([':now' => $today]);
            $caVencidos = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM EPIs 
                 WHERE epi_status != 'INATIVO'
                 AND epi_tipo_item = 'EPI_COM_CA'
                 AND epi_vencimento_ca IS NOT NULL 
                 AND epi_vencimento_ca >= :now 
                 AND epi_vencimento_ca <= :limit"
            );
            $stmt->execute([':now' => $today, ':limit' => $sevenDaysAhead]);
            $caAVencer7Dias = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // 2. Vida Útil
            $stmt = $this->db->prepare(
                "SELECT 
                    i.item_id, i.epi_id, e.entr_data_entrega, ep.epi_vida_util, 
                    ep.epi_vida_util_unidade, ep.epi_vida_util_tipo, ep.epi_vida_util_alerta,
                    e.fun_id
                 FROM Itens_Entrega i
                 INNER JOIN Entrega_EPIs e ON i.entr_id = e.entr_id
                 INNER JOIN EPIs ep ON i.epi_id = ep.epi_id
                 WHERE e.entr_status = 'FINALIZADA'
                 AND i.item_data_devolucao IS NULL
                 AND (i.item_devolucao_motivo IS NULL OR i.item_devolucao_motivo = '')
                 AND (i.item_devolucao_vinculo_entrega_id IS NULL OR i.item_devolucao_vinculo_entrega_id = 0)
                 AND ep.epi_vida_util_tipo = 'CONTROLADO'
                 AND ep.epi_vida_util IS NOT NULL
                 AND ep.epi_vida_util > 0"
            );
            $stmt->execute();
            $itensEmUso = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $vidaUtilVencida = 0;
            $vidaUtilTrocaProxima = 0;
            $funIdsVencidos = [];
            $funIdsProximos = [];

            foreach ($itensEmUso as $item) {
                $dataEntrega = new \DateTime($item['entr_data_entrega']);
                $vidaUtilDias = $this->calcularVidaUtilEmDias(
                    (int)$item['epi_vida_util'],
                    $item['epi_vida_util_unidade']
                );

                if ($vidaUtilDias <= 0) continue;

                $dataTroca = clone $dataEntrega;
                $dataTroca->modify("+{$vidaUtilDias} days");
                $hoje = new \DateTime($today);

                if ($dataTroca < $hoje) {
                    $vidaUtilVencida++;
                    $funIdsVencidos[$item['fun_id']] = true;
                } else {
                    $diasParaTroca = $hoje->diff($dataTroca)->days;
                    $diasAlerta = !empty($item['epi_vida_util_alerta']) ? (int)$item['epi_vida_util_alerta'] : 30;
                    if ($diasParaTroca <= $diasAlerta) {
                        $vidaUtilTrocaProxima++;
                        $funIdsProximos[$item['fun_id']] = true;
                    }
                }
            }

            $funcionariosEpisVencidos = count($funIdsVencidos);
            $funcionariosEpisProximosTroca = count($funIdsProximos);

            // 3. Vida útil não cadastrada
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM EPIs 
                 WHERE epi_vida_util_tipo = 'CONTROLADO' 
                 AND (epi_vida_util IS NULL OR epi_vida_util <= 0)
                 AND epi_status != 'INATIVO'"
            );
            $stmt->execute();
            $vidaUtilNaoCadastrada = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // 4. Sem Rastreabilidade
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM EPIs 
                 WHERE epi_tipo_item = 'ITEM_SEGURANCA_SEM_CA'
                 AND (epi_numero_lote IS NULL OR epi_numero_lote = '')
                 AND (epi_modelo IS NULL OR epi_modelo = '')
                 AND (epi_identificacao IS NULL OR epi_identificacao = '')
                 AND (epi_ref_fornecedor IS NULL OR epi_ref_fornecedor = '')
                 AND epi_status != 'INATIVO'"
            );
            $stmt->execute();
            $semRastreabilidade = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // 5. Cards Principais
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM EPIs 
                 WHERE epi_status = 'VENCIDO' 
                 OR (epi_vencimento_ca IS NOT NULL AND epi_vencimento_ca < :now)"
            );
            $stmt->execute([':now' => $today]);
            $episVencidos = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $aVencer7Dias = $caAVencer7Dias;

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM Entrega_EPIs 
                 WHERE entr_status = 'FINALIZADA' 
                 AND entr_data_entrega >= :todayStart"
            );
            $stmt->execute([':todayStart' => $todayStart]);
            $entregasHoje = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM Funcionarios f 
                 WHERE f.fun_situacao NOT IN ('INATIVO', 'DEMITIDO')
                 AND NOT EXISTS (
                     SELECT 1 FROM Assinatura_Eletronica a WHERE a.fun_id = f.fun_id
                 )"
            );
            $stmt->execute();
            $funcionariosSemPin = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $pendencias = $episVencidos + $funcionariosSemPin + $vidaUtilNaoCadastrada + $semRastreabilidade;

            // 6. Custos
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(i.item_quantidade * COALESCE(i.item_epi_valor_snapshot, ep.epi_valor)), 0) as total
                 FROM Itens_Entrega i
                 INNER JOIN EPIs ep ON i.epi_id = ep.epi_id
                 INNER JOIN Entrega_EPIs ent ON i.entr_id = ent.entr_id
                 WHERE ent.entr_status = 'FINALIZADA'
                 AND ent.entr_data_entrega >= :inicioMes"
            );
            $stmt->execute([':inicioMes' => $firstDayOfMonth]);
            $custoMensal = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $mesesPt = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
            $mesAtual = $mesesPt[(int)date('n') - 1];
            $custoMensalRotulo = "Custo Mensal ({$mesAtual})";

            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(i.item_quantidade * COALESCE(i.item_epi_valor_snapshot, ep.epi_valor)), 0) as total
                 FROM Itens_Entrega i
                 INNER JOIN EPIs ep ON i.epi_id = ep.epi_id
                 INNER JOIN Entrega_EPIs ent ON i.entr_id = ent.entr_id
                 WHERE ent.entr_status = 'FINALIZADA'"
            );
            $stmt->execute();
            $custoAcumulado = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->prepare(
                "SELECT MIN(entr_data_entrega) as primeira FROM Entrega_EPIs WHERE entr_status = 'FINALIZADA'"
            );
            $stmt->execute();
            $primeiraEntrega = $stmt->fetch(PDO::FETCH_ASSOC)['primeira'];
            if ($primeiraEntrega) {
                $dtPrimeira = new \DateTime($primeiraEntrega);
                $custoAcumuladoRotulo = "Acumulado (desde " . $dtPrimeira->format('d/m/Y') . ")";
            } else {
                $custoAcumuladoRotulo = "Acumulado (Histórico)";
            }

            // 7. Conformidade
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM Funcionarios");
            $stmt->execute();
            $totalFuncionarios = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM Funcionarios WHERE fun_situacao = 'ATIVO'"
            );
            $stmt->execute();
            $funcionariosEmDia = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $conformidadePercentual = $totalFuncionarios > 0
                ? (int)(($funcionariosEmDia * 100) / $totalFuncionarios)
                : 100;

            $conformidadeDetalhe = "{$funcionariosEmDia} de {$totalFuncionarios} funcionários ativos com EPIs em dia";

            // 8. Gráfico Semanal
            $entregasSemana = [];
            for ($i = 6; $i >= 0; $i--) {
                $dia = date('Y-m-d', strtotime("-{$i} days"));
                $diaInicio = $dia . ' 00:00:00';
                $diaFim = $dia . ' 23:59:59';

                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) as total FROM Entrega_EPIs 
                     WHERE entr_status = 'FINALIZADA'
                     AND entr_data_entrega >= :inicio 
                     AND entr_data_entrega <= :fim"
                );
                $stmt->execute([':inicio' => $diaInicio, ':fim' => $diaFim]);
                $entregasSemana[] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
            }

            // 9. Top 5 EPIs
            $stmt = $this->db->prepare(
                "SELECT CONCAT(ep.epi_nome, ' (', SUM(i.item_quantidade), ' entregas)') as label
                 FROM Itens_Entrega i
                 INNER JOIN EPIs ep ON i.epi_id = ep.epi_id
                 INNER JOIN Entrega_EPIs ent ON i.entr_id = ent.entr_id
                 WHERE ent.entr_status = 'FINALIZADA'
                 GROUP BY i.epi_id, ep.epi_nome
                 ORDER BY SUM(i.item_quantidade) DESC
                 LIMIT 5"
            );
            $stmt->execute();
            $top5Epis = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            Response::json(true, "Dashboard centralizado carregado com sucesso.", [
                'ca_vencidos' => $caVencidos,
                'ca_a_vencer_7_dias' => $caAVencer7Dias,
                'vida_util_vencida' => $vidaUtilVencida,
                'vida_util_troca_proxima' => $vidaUtilTrocaProxima,
                'funcionarios_epis_vencidos' => $funcionariosEpisVencidos,
                'funcionarios_epis_proximos_troca' => $funcionariosEpisProximosTroca,
                'vida_util_nao_cadastrada' => $vidaUtilNaoCadastrada,
                'sem_rastreabilidade' => $semRastreabilidade,
                'epis_vencidos' => $episVencidos,
                'a_vencer_7_dias' => $aVencer7Dias,
                'entregas_hoje' => $entregasHoje,
                'pendencias' => $pendencias,
                'custo_mensal' => $custoMensal,
                'custo_mensal_rotulo' => $custoMensalRotulo,
                'custo_acumulado' => $custoAcumulado,
                'custo_acumulado_rotulo' => $custoAcumuladoRotulo,
                'funcionarios_sem_pin' => $funcionariosSemPin,
                'conformidade_percentual' => $conformidadePercentual,
                'conformidade_detalhe' => $conformidadeDetalhe,
                'total_funcionarios' => $totalFuncionarios,
                'funcionarios_em_dia' => $funcionariosEmDia,
                'entregas_semana' => $entregasSemana,
                'top_5_epis' => $top5Epis
            ]);
        } catch (Exception $e) {
            Response::json(false, "Erro ao carregar dashboard: " . $e->getMessage(), null, 500);
        }
    }

    private function calcularVidaUtilEmDias(int $valor, ?string $unidade): int {
        if ($valor <= 0) return 0;
        $unidade = strtoupper(trim($unidade ?? 'DIAS'));
        switch ($unidade) {
            case 'MESES': case 'MES': return $valor * 30;
            case 'ANOS': case 'ANO': return $valor * 365;
            case 'SEMANAS': case 'SEMANA': return $valor * 7;
            case 'DIAS': case 'DIA': default: return $valor;
        }
    }
}
