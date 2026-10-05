<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Config\Database;
use PDO;
use DateTime;
use DateTimeZone;
use Exception;

class DashboardController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * GET /dashboard
     */
    public function index(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);

        try {
            date_default_timezone_set('America/Sao_Paulo');
            $tzSp = new DateTimeZone('America/Sao_Paulo');
            $tzUtc = new DateTimeZone('UTC');

            $nowDt = new DateTime('now', $tzSp);
            $now = $nowDt->format('Y-m-d H:i:s');
            $today = $nowDt->format('Y-m-d');

            $todayStartDt = new DateTime('today', $tzSp);
            $todayStartUtc = clone $todayStartDt;
            $todayStartUtc->setTimezone($tzUtc);
            $todayStartSql = $todayStartUtc->format('Y-m-d H:i:s');

            $sevenDaysAhead = date('Y-m-d', strtotime('+7 days'));
            $firstDayOfMonth = date('Y-m-01') . ' 00:00:00';

            // 1. ALERTAS DE C.A. (Certificado de Aprovacao)
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM epis 
                 WHERE epi_status != 'INATIVO' 
                 AND epi_tipo_item = 'EPI_COM_CA'
                 AND (epi_status = 'VENCIDO' OR (epi_vencimento_ca IS NOT NULL AND epi_vencimento_ca < :now))"
            );
            $stmt->execute([':now' => $today]);
            $caVencidos = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM epis 
                 WHERE epi_status != 'INATIVO'
                 AND epi_tipo_item = 'EPI_COM_CA'
                 AND epi_vencimento_ca IS NOT NULL 
                 AND epi_vencimento_ca >= :now 
                 AND epi_vencimento_ca <= :limit"
            );
            $stmt->execute([':now' => $today, ':limit' => $sevenDaysAhead]);
            $caAVencer7Dias = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // 2. VIDA UTIL - EPIs em uso com vida util vencida/proxima
            // Deduplica por (fun_id, epi_id) para considerar apenas o item ativo mais recente por funcionario/EPI
            $stmt = $this->db->prepare(
                "SELECT 
                    i.item_id, i.epi_id, e.entr_data_entrega, ep.epi_vida_util, 
                    ep.epi_vida_util_unidade, ep.epi_vida_util_tipo, ep.epi_vida_util_alerta,
                    e.fun_id
                 FROM itens_entrega i
                 INNER JOIN entrega_epis e ON i.entr_id = e.entr_id
                 INNER JOIN epis ep ON i.epi_id = ep.epi_id
                 INNER JOIN (
                     SELECT e2.fun_id, i2.epi_id, MAX(i2.item_id) as max_item_id
                     FROM itens_entrega i2
                     INNER JOIN entrega_epis e2 ON i2.entr_id = e2.entr_id
                     WHERE e2.entr_status = 'FINALIZADA'
                     AND i2.item_data_devolucao IS NULL
                     AND (i2.item_devolucao_motivo IS NULL OR i2.item_devolucao_motivo = '')
                     AND (i2.item_devolucao_vinculo_entrega_id IS NULL OR i2.item_devolucao_vinculo_entrega_id = 0)
                     GROUP BY e2.fun_id, i2.epi_id
                 ) latest ON i.item_id = latest.max_item_id
                 WHERE e.entr_status = 'FINALIZADA'
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
                $dataEntrega = new DateTime($item['entr_data_entrega']);
                $vidaUtilDias = $this->calcularVidaUtilEmDias(
                    (int)$item['epi_vida_util'],
                    $item['epi_vida_util_unidade']
                );

                if ($vidaUtilDias <= 0) continue;

                $dataTroca = clone $dataEntrega;
                $dataTroca->modify("+" . $vidaUtilDias . " days");
                $hoje = new DateTime($today);

                if ($dataTroca < $hoje) {
                    $vidaUtilVencida++;
                    $funIdsVencidos[$item['fun_id']] = true;
                } else {
                    $diasParaTroca = (int)$hoje->diff($dataTroca)->format('%r%a');
                    $diasAlerta = !empty($item['epi_vida_util_alerta']) ? (int)$item['epi_vida_util_alerta'] : 30;
                    if ($diasParaTroca >= 0 && $diasParaTroca <= $diasAlerta) {
                        $vidaUtilTrocaProxima++;
                        $funIdsProximos[$item['fun_id']] = true;
                    }
                }
            }

            $funcionariosEpisVencidos = count($funIdsVencidos);
            $funcionariosEpisProximosTroca = count($funIdsProximos);

            // 3. VIDA UTIL NAO CADASTRADA
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM epis 
                 WHERE epi_vida_util_tipo = 'CONTROLADO' 
                 AND (epi_vida_util IS NULL OR epi_vida_util <= 0)
                 AND epi_status != 'INATIVO'"
            );
            $stmt->execute();
            $vidaUtilNaoCadastrada = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // 4. ITENS SEM RASTREABILIDADE
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM epis 
                 WHERE (epi_tipo_item = 'EPI_SEM_CA' OR epi_tipo_item IS NULL OR epi_tipo_item = '')
                 AND (epi_vida_util_tipo = 'NAO_CONTROLADO' OR epi_vida_util_tipo IS NULL OR epi_vida_util_tipo = '')
                 AND epi_status != 'INATIVO'"
            );
            $stmt->execute();
            $semRastreabilidade = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $episVencidos = $caVencidos + $vidaUtilVencida;
            $aVencer7Dias = $caAVencer7Dias + $vidaUtilTrocaProxima;

            // 5. ENTREGAS HOJE
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM entrega_epis 
                 WHERE entr_status = 'FINALIZADA' 
                 AND entr_data_entrega >= :inicio"
            );
            $stmt->execute([':inicio' => $todayStartSql]);
            $entregasHoje = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // PENDENCIAS
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM entrega_epis WHERE entr_status = 'PENDENTE'"
            );
            $stmt->execute();
            $pendencias = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // 6. CUSTOS (usando ep.epi_valor da tabela epis)
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(i.item_quantidade * ep.epi_valor), 0) as total 
                 FROM itens_entrega i 
                 INNER JOIN epis ep ON i.epi_id = ep.epi_id
                 INNER JOIN entrega_epis e ON i.entr_id = e.entr_id 
                 WHERE e.entr_status = 'FINALIZADA' 
                 AND e.entr_data_entrega >= :inicio"
            );
            $stmt->execute([':inicio' => $firstDayOfMonth]);
            $custoMensal = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $custoMensalRotulo = "Custo Mensal (" . date('m/Y') . ")";

            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(i.item_quantidade * ep.epi_valor), 0) as total 
                 FROM itens_entrega i 
                 INNER JOIN epis ep ON i.epi_id = ep.epi_id
                 INNER JOIN entrega_epis e ON i.entr_id = e.entr_id 
                 WHERE e.entr_status = 'FINALIZADA'"
            );
            $stmt->execute();
            $custoAcumulado = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->prepare(
                "SELECT MIN(entr_data_entrega) as primeira FROM entrega_epis WHERE entr_status = 'FINALIZADA'"
            );
            $stmt->execute();
            $primeiraEntrega = $stmt->fetch(PDO::FETCH_ASSOC)['primeira'];
            if ($primeiraEntrega) {
                $dtPrimeira = new DateTime($primeiraEntrega);
                $custoAcumuladoRotulo = "Acumulado (desde " . $dtPrimeira->format('d/m/Y') . ")";
            } else {
                $custoAcumuladoRotulo = "Acumulado (Historico)";
            }

            // FUNCIONARIOS SEM PIN (usando tabela assinatura_eletronica)
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM funcionarios f 
                 LEFT JOIN assinatura_eletronica a ON f.fun_id = a.fun_id 
                 WHERE f.fun_situacao = 'ATIVO' 
                 AND a.ass_id IS NULL"
            );
            $stmt->execute();
            $funcionariosSemPin = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // 7. CONFORMIDADE
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM funcionarios WHERE fun_situacao = 'ATIVO'");
            $stmt->execute();
            $totalFuncionarios = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $funcionariosEmDia = max(0, $totalFuncionarios - $funcionariosEpisVencidos);

            $conformidadePercentual = $totalFuncionarios > 0
                ? (int)(($funcionariosEmDia * 100) / $totalFuncionarios)
                : 100;

            $conformidadeDetalhe = "{$funcionariosEmDia} de {$totalFuncionarios} funcionarios ativos com EPIs em dia";

            // 8. GRAFICO SEMANAL
            $entregasSemana = [];
            for ($i = 6; $i >= 0; $i--) {
                $dia = date('Y-m-d', strtotime("-" . $i . " days"));
                $diaInicio = $dia . ' 00:00:00';
                $diaFim = $dia . ' 23:59:59';

                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) as total FROM entrega_epis 
                     WHERE entr_status = 'FINALIZADA'
                     AND entr_data_entrega >= :inicio 
                     AND entr_data_entrega <= :fim"
                );
                $stmt->execute([':inicio' => $diaInicio, ':fim' => $diaFim]);
                $entregasSemana[] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
            }

            // 9. TOP 5 EPIs MAIS CONSUMIDOS
            $stmt = $this->db->prepare(
                "SELECT CONCAT(ep.epi_nome, ' (', SUM(i.item_quantidade), ' entregas)') as label
                 FROM itens_entrega i
                 INNER JOIN epis ep ON i.epi_id = ep.epi_id
                 GROUP BY i.epi_id, ep.epi_nome
                 ORDER BY SUM(i.item_quantidade) DESC
                 LIMIT 5"
            );
            $stmt->execute();
            $top5Epis = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            // MONTAR RESPOSTA
            $data = [
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
            ];

            Response::json(true, 'Dashboard carregado com sucesso.', $data, 200);

        } catch (Exception $e) {
            Response::json(false, 'Erro ao calcular dashboard: ' . $e->getMessage(), null, 500);
        }
    }

    public function resumo(): void {
        $this->index();
    }

    public function custos(): void {
        $this->index();
    }

    public function topEpis(): void {
        $this->index();
    }

    public function pendencias(): void {
        $this->index();
    }

    /**
     * Converte vida util para dias com base na unidade cadastrada.
     */
    private function calcularVidaUtilEmDias(int $valor, ?string $unidade): int
    {
        if ($valor <= 0) return 0;

        $unidade = strtoupper(trim($unidade ?? 'DIAS'));
        switch ($unidade) {
            case 'MESES':
            case 'MES':
                return $valor * 30;
            case 'ANOS':
            case 'ANO':
                return $valor * 365;
            case 'SEMANAS':
            case 'SEMANA':
                return $valor * 7;
            case 'DIAS':
            case 'DIA':
            default:
                return $valor;
        }
    }
}
