<?php
/**
 * DashboardController.php
 * 
 * Endpoint centralizado GET /dashboard que retorna TODOS os indicadores
 * do Dashboard pré-calculados pelo servidor usando o banco central.
 * 
 * OBJETIVO: Garantir que TODOS os dispositivos (tablets, celulares, emuladores)
 * mostrem exatamente os mesmos valores, eliminando divergências causadas por
 * cache local (SQLite/Room) desatualizado.
 * 
 * INSTRUÇÕES DE DEPLOY:
 * 1. Copiar este arquivo para: gestao_epi_api_7/controllers/DashboardController.php
 * 2. Adicionar a rota no index.php ou routes/api.php:
 *    $app->get('/dashboard', 'DashboardController:index');
 * 3. Deploy no Render
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController
{
    private $db;

    public function __construct($container)
    {
        $this->db = $container->get('db');
    }

    public function index(Request $request, Response $response)
    {
        try {
            date_default_timezone_set('America/Sao_Paulo');
            $tzSp = new \DateTimeZone('America/Sao_Paulo');
            $tzUtc = new \DateTimeZone('UTC');

            $nowDt = new \DateTime('now', $tzSp);
            $now = $nowDt->format('Y-m-d H:i:s');
            $today = $nowDt->format('Y-m-d');

            // Início do dia atual em São Paulo (00:00:00 BRT) e sua conversão em UTC para suporte universal de BD
            $todayStartDt = new \DateTime('today', $tzSp);
            $todayStart = $todayStartDt->format('Y-m-d H:i:s');
            $todayStartUtc = clone $todayStartDt;
            $todayStartUtc->setTimezone($tzUtc);
            $todayStartSql = $todayStartUtc->format('Y-m-d H:i:s');

            $sevenDaysAhead = date('Y-m-d', strtotime('+7 days'));
            $firstDayOfMonth = date('Y-m-01') . ' 00:00:00';

            // ═══════════════════════════════════════════════════════════
            // 1. ALERTAS DE C.A. (Certificado de Aprovação)
            // ═══════════════════════════════════════════════════════════

            // EPIs com C.A. vencido
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM epis 
                 WHERE epi_status != 'INATIVO' 
                 AND epi_tipo_item = 'EPI_COM_CA'
                 AND (epi_status = 'VENCIDO' OR (epi_vencimento_ca IS NOT NULL AND epi_vencimento_ca < :now))"
            );
            $stmt->execute([':now' => $today]);
            $caVencidos = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // EPIs com C.A. a vencer nos próximos 7 dias
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

            // ═══════════════════════════════════════════════════════════
            // 2. VIDA ÚTIL - EPIs em uso com vida útil vencida/próxima
            // ═══════════════════════════════════════════════════════════

            $stmt = $this->db->prepare(
                "SELECT 
                    i.item_id, i.epi_id, e.entr_data_entrega, ep.epi_vida_util, 
                    ep.epi_vida_util_unidade, ep.epi_vida_util_tipo, ep.epi_vida_util_alerta,
                    e.fun_id
                 FROM itens_entrega i
                 INNER JOIN entrega_epis e ON i.entr_id = e.entr_id
                 INNER JOIN epis ep ON i.epi_id = ep.epi_id
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
                $dataEntrega = new DateTime($item['entr_data_entrega']);
                $vidaUtilDias = $this->calcularVidaUtilEmDias(
                    (int)$item['epi_vida_util'],
                    $item['epi_vida_util_unidade']
                );

                if ($vidaUtilDias <= 0) continue;

                $dataTroca = clone $dataEntrega;
                $dataTroca->modify("+{$vidaUtilDias} days");
                $hoje = new DateTime($today);

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

            // ═══════════════════════════════════════════════════════════
            // 3. VIDA ÚTIL NÃO CADASTRADA
            // ═══════════════════════════════════════════════════════════
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM epis 
                 WHERE epi_vida_util_tipo = 'CONTROLADO' 
                 AND (epi_vida_util IS NULL OR epi_vida_util <= 0)
                 AND epi_status != 'INATIVO'"
            );
            $stmt->execute();
            $vidaUtilNaoCadastrada = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // ═══════════════════════════════════════════════════════════
            // 4. ITENS SEM RASTREABILIDADE
            // ═══════════════════════════════════════════════════════════
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM epis 
                 WHERE epi_tipo_item = 'ITEM_SEGURANCA_SEM_CA'
                 AND (epi_numero_lote IS NULL OR epi_numero_lote = '')
                 AND (epi_modelo IS NULL OR epi_modelo = '')
                 AND (epi_identificacao IS NULL OR epi_identificacao = '')
                 AND (epi_ref_fornecedor IS NULL OR epi_ref_fornecedor = '')
                 AND epi_status != 'INATIVO'"
            );
            $stmt->execute();
            $semRastreabilidade = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // ═══════════════════════════════════════════════════════════
            // 5. CARDS PRINCIPAIS
            // ═══════════════════════════════════════════════════════════

            // EPIs Vencidos (card)
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM epis 
                 WHERE epi_status = 'VENCIDO' 
                 OR (epi_vencimento_ca IS NOT NULL AND epi_vencimento_ca < :now)"
            );
            $stmt->execute([':now' => $today]);
            $caVencidos = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $episVencidos = $caVencidos;
            $aVencer7Dias = $caAVencer7Dias + $vidaUtilTrocaProxima;

            // Entregas Hoje em São Paulo (UTC-3)
            $todayEnd = $today . ' 23:59:59';
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM entrega_epis 
                 WHERE entr_status = 'FINALIZADA' 
                 AND (entr_data_entrega >= :todayStart AND entr_data_entrega <= :todayEnd)"
            );
            $stmt->execute([':todayStart' => $todayStart, ':todayEnd' => $todayEnd]);
            $entregasHoje = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Funcionários sem PIN
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM funcionarios f 
                 WHERE f.fun_situacao NOT IN ('INATIVO', 'DEMITIDO')
                 AND NOT EXISTS (
                     SELECT 1 FROM assinatura_eletronica a WHERE a.fun_id = f.fun_id
                 )"
            );
            $stmt->execute();
            $funcionariosSemPin = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Pendências
            $pendencias = $caVencidos + $funcionariosSemPin + $vidaUtilNaoCadastrada + $semRastreabilidade;

            // ═══════════════════════════════════════════════════════════
            // 6. CUSTOS
            // ═══════════════════════════════════════════════════════════

            // Custo Mensal
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(i.item_quantidade * ep.epi_valor), 0) as total
                 FROM itens_entrega i
                 INNER JOIN epis ep ON i.epi_id = ep.epi_id
                 INNER JOIN entrega_epis ent ON i.entr_id = ent.entr_id
                 WHERE ent.entr_status = 'FINALIZADA'
                 AND ent.entr_data_entrega >= :inicioMes"
            );
            $stmt->execute([':inicioMes' => $firstDayOfMonth]);
            $custoMensal = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $mesesPt = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
            $mesAtual = $mesesPt[(int)date('n') - 1];
            $custoMensalRotulo = "Custo Mensal ({$mesAtual})";

            // Custo Acumulado
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(i.item_quantidade * ep.epi_valor), 0) as total
                 FROM itens_entrega i
                 INNER JOIN epis ep ON i.epi_id = ep.epi_id
                 INNER JOIN entrega_epis ent ON i.entr_id = ent.entr_id
                 WHERE ent.entr_status = 'FINALIZADA'"
            );
            $stmt->execute();
            $custoAcumulado = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Data da primeira entrega
            $stmt = $this->db->prepare(
                "SELECT MIN(entr_data_entrega) as primeira FROM entrega_epis WHERE entr_status = 'FINALIZADA'"
            );
            $stmt->execute();
            $primeiraEntrega = $stmt->fetch(PDO::FETCH_ASSOC)['primeira'];
            if ($primeiraEntrega) {
                $dtPrimeira = new DateTime($primeiraEntrega);
                $custoAcumuladoRotulo = "Acumulado (desde " . $dtPrimeira->format('d/m/Y') . ")";
            } else {
                $custoAcumuladoRotulo = "Acumulado (Histórico)";
            }

            // ═══════════════════════════════════════════════════════════
            // 7. CONFORMIDADE
            // ═══════════════════════════════════════════════════════════
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM funcionarios");
            $stmt->execute();
            $totalFuncionarios = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM funcionarios WHERE fun_situacao = 'ATIVO'"
            );
            $stmt->execute();
            $funcionariosEmDia = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $conformidadePercentual = $totalFuncionarios > 0
                ? (int)(($funcionariosEmDia * 100) / $totalFuncionarios)
                : 100;

            $conformidadeDetalhe = "{$funcionariosEmDia} de {$totalFuncionarios} funcionários ativos com EPIs em dia";

            // ═══════════════════════════════════════════════════════════
            // 8. GRÁFICO SEMANAL
            // ═══════════════════════════════════════════════════════════
            $entregasSemana = [];
            for ($i = 6; $i >= 0; $i--) {
                $dia = date('Y-m-d', strtotime("-{$i} days"));
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

            // ═══════════════════════════════════════════════════════════
            // 9. TOP 5 EPIs MAIS CONSUMIDOS
            // ═══════════════════════════════════════════════════════════
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

            // ═══════════════════════════════════════════════════════════
            // MONTAR RESPOSTA
            // ═══════════════════════════════════════════════════════════
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

            $payload = json_encode([
                'success' => true,
                'message' => 'Dashboard carregado com sucesso.',
                'data' => $data
            ]);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {
            $payload = json_encode([
                'success' => false,
                'message' => 'Erro ao calcular dashboard: ' . $e->getMessage(),
                'data' => null
            ]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Converte vida útil para dias com base na unidade cadastrada.
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
