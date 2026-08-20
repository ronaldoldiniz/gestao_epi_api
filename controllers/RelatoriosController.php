<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Core\Audit;
use Config\Database;
use Models\EntregaEpi;
use Models\ItemEntrega;
use Models\Epi;
use Models\Funcionario;
use PDO;
use Exception;

class RelatoriosController {
    private PDO $db;
    private EntregaEpi $entregaModel;
    private ItemEntrega $itemModel;
    private Epi $epiModel;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->entregaModel = new EntregaEpi();
        $this->itemModel = new ItemEntrega();
        $this->epiModel = new Epi();
    }

    /**
     * GET /relatorios/entregas
     * Relatório geral consolidado de entregas
     */
    public function entregasGerais(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR', 'RH_ADMINISTRATIVO']);
        
        $sql = "SELECT e.entr_id, f.fun_nome, u.usu_login, e.entr_data_entrega, e.entr_status, e.entr_motivo,
                       (SELECT COUNT(*) FROM itens_entrega i WHERE i.entr_id = e.entr_id) as total_itens
                FROM entrega_epis e
                JOIN funcionarios f ON e.fun_id = f.fun_id
                JOIN usuarios u ON e.usu_id = u.usu_id
                ORDER BY e.entr_data_entrega DESC";
        
        $stmt = $this->db->query($sql);
        $relatorio = $stmt->fetchAll();

        Response::json(true, "Relatório de entregas gerado com sucesso.", $relatorio);
    }

    /**
     * GET /relatorios/entregas/funcionario/{fun_id}
     */
    public function entregasPorFuncionario(string $funId): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR', 'RH_ADMINISTRATIVO']);
        $fId = (int)$funId;

        $funcModel = new Funcionario();
        $funcionario = $funcModel->findById($fId, true);
        if (!$funcionario) {
            Response::json(false, "Funcionário não encontrado.", null, 404);
        }

        $entregas = $this->entregaModel->findByFuncionarioId($fId);
        foreach ($entregas as &$entrega) {
            $entrega['itens'] = $this->itemModel->findByEntregaId((int)$entrega['entr_id']);
        }

        Response::json(true, "Relatório de entregas do funcionário gerado com sucesso.", [
            'funcionario' => $funcionario,
            'entregas' => $entregas
        ]);
    }

    /**
     * GET /relatorios/epis-vencidos
     * Relatório de EPIs que estão com status VENCIDO ou cuja validade expirou (baseada no C.A.)
     */
    public function episVencidos(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR', 'RH_ADMINISTRATIVO']);
        
        $sql = "SELECT * FROM epis WHERE epi_vencimento_ca < CURDATE() OR epi_status = 'VENCIDO'";
        $stmt = $this->db->query($sql);
        $vencidos = $stmt->fetchAll();

        Response::json(true, "Relatório de EPIs vencidos gerado.", $vencidos);
    }

    /**
     * GET /relatorios/ca-vencidos
     * Relatório focado estritamente no vencimento do C.A. da tabela de EPIs
     */
    public function caVencidos(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR', 'RH_ADMINISTRATIVO']);
        
        $sql = "SELECT epi_id, epi_nome, epi_ca, epi_vencimento_ca, epi_fabricante, epi_status 
                FROM epis 
                WHERE epi_vencimento_ca < CURDATE() AND epi_status != 'INATIVO'";
        
        $stmt = $this->db->query($sql);
        $vencidos = $stmt->fetchAll();

        Response::json(true, "Relatório de C.A. vencidos gerado.", $vencidos);
    }

    /**
     * GET /relatorios/custo-mensal
     * Relatório financeiro agrupado por ano/mês dos custos de entregas de EPIs
     */
    public function custoMensal(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'GESTOR']);

        $sql = "SELECT 
                    DATE_FORMAT(e.entr_data_entrega, '%Y-%m') AS mes,
                    SUM(i.item_quantidade * COALESCE(i.item_epi_valor_snapshot, ep.epi_valor)) AS custo_total,
                    COUNT(DISTINCT e.entr_id) as total_entregas,
                    SUM(i.item_quantidade) as total_itens_entregues
                FROM itens_entrega i
                JOIN entrega_epis e ON i.entr_id = e.entr_id
                JOIN epis ep ON i.epi_id = ep.epi_id
                WHERE e.entr_status = 'FINALIZADA'
                GROUP BY DATE_FORMAT(e.entr_data_entrega, '%Y-%m')
                ORDER BY mes DESC";
        
        $stmt = $this->db->query($sql);
        $custos = $stmt->fetchAll();

        Response::json(true, "Relatório de custo mensal gerado.", $custos);
    }

    /**
     * GET /relatorios/epis/geral
     * Relatório geral consolidado de fornecimento de EPIs
     */
    public function relatorioGeralEpis(): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR', 'RH_ADMINISTRATIVO']);
        $userProfile = $currentUser['usu_perfil'] ?? '';
        $canViewCosts = in_array($userProfile, ['ADMINISTRADOR', 'GESTOR'], true);

        // Parâmetros obrigatórios
        $dataInicial = $_GET['data_inicial'] ?? '';
        $dataFinal = $_GET['data_final'] ?? '';

        if (empty($dataInicial) || empty($dataFinal)) {
            Response::json(false, "As datas de início e fim são obrigatórias.", null, 400);
        }

        // Valida se as datas estão corretas
        if (!preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?$/', $dataInicial) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?$/', $dataFinal)) {
            Response::json(false, "Formato de data inválido.", null, 400);
        }

        // Ajusta horas se necessário
        if (strlen($dataInicial) === 10) {
            $dataInicial .= ' 00:00:00';
        }
        if (strlen($dataFinal) === 10) {
            $dataFinal .= ' 23:59:59';
        }

        if (strtotime($dataInicial) > strtotime($dataFinal)) {
            Response::json(false, "A data inicial não pode ser posterior à data final.", null, 400);
        }

        // Parâmetros opcionais de filtros
        $funcId = isset($_GET['funcionario_id']) && $_GET['funcionario_id'] !== '' ? (int)$_GET['funcionario_id'] : null;
        $departamento = isset($_GET['departamento']) && $_GET['departamento'] !== '' ? trim($_GET['departamento']) : null;
        $cargo = isset($_GET['cargo']) && $_GET['cargo'] !== '' ? trim($_GET['cargo']) : null;
        $epiId = isset($_GET['epi_id']) && $_GET['epi_id'] !== '' ? (int)$_GET['epi_id'] : null;
        $categoria = isset($_GET['categoria']) && $_GET['categoria'] !== '' ? trim($_GET['categoria']) : null;
        $motivo = isset($_GET['motivo']) && $_GET['motivo'] !== '' ? trim($_GET['motivo']) : null;
        $usuarioId = isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '' ? (int)$_GET['usuario_id'] : null;
        $itemComCa = isset($_GET['item_com_ca']) && $_GET['item_com_ca'] !== '' ? (int)$_GET['item_com_ca'] : null;

        // Status das entregas a considerar (Padrão: FINALIZADA)
        $statusEntrega = isset($_GET['status_entrega']) && $_GET['status_entrega'] !== '' ? trim($_GET['status_entrega']) : 'FINALIZADA';

        // Paginação
        $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $limite = isset($_GET['limite']) ? max(1, (int)$_GET['limite']) : 25;
        $offset = ($pagina - 1) * $limite;

        // Ordenação
        $ordenacao = $_GET['ordenacao'] ?? 'data_desc';
        $orderBy = "e.entr_data_entrega DESC";
        if ($ordenacao === 'data_asc') $orderBy = "e.entr_data_entrega ASC";
        else if ($ordenacao === 'funcionario') $orderBy = "f.fun_nome ASC";
        else if ($ordenacao === 'epi') $orderBy = "COALESCE(i.item_epi_nome_snapshot, ep.epi_nome) ASC";
        else if ($ordenacao === 'setor') $orderBy = "f.fun_departamento ASC";
        else if ($ordenacao === 'quantidade') $orderBy = "i.item_quantidade DESC";
        else if ($ordenacao === 'motivo') $orderBy = "COALESCE(i.item_motivo_entrega, e.entr_motivo) ASC";

        // Query Base
        $whereClauses = ["e.entr_data_entrega BETWEEN :data_inicial AND :data_final"];
        $params = [
            ':data_inicial' => $dataInicial,
            ':data_final' => $dataFinal
        ];

        // Adiciona filtros se fornecidos
        if ($statusEntrega !== 'TODAS') {
            $whereClauses[] = "e.entr_status = :status_entrega";
            $params[':status_entrega'] = $statusEntrega;
        }
        if ($funcId !== null) {
            $whereClauses[] = "e.fun_id = :func_id";
            $params[':func_id'] = $funcId;
        }
        if ($departamento !== null) {
            $whereClauses[] = "f.fun_departamento LIKE :departamento";
            $params[':departamento'] = '%' . $departamento . '%';
        }
        if ($cargo !== null) {
            $whereClauses[] = "f.fun_cargo LIKE :cargo";
            $params[':cargo'] = '%' . $cargo . '%';
        }
        if ($epiId !== null) {
            $whereClauses[] = "i.epi_id = :epi_id";
            $params[':epi_id'] = $epiId;
        }
        if ($categoria !== null) {
            $whereClauses[] = "COALESCE(ep.epi_tipo_item, 'EPI_COM_CA') = :categoria";
            $params[':categoria'] = $categoria;
        }
        if ($motivo !== null) {
            $whereClauses[] = "(i.item_motivo_entrega LIKE :motivo OR e.entr_motivo LIKE :motivo)";
            $params[':motivo'] = '%' . $motivo . '%';
        }
        if ($usuarioId !== null) {
            $whereClauses[] = "e.usu_id = :usuario_id";
            $params[':usuario_id'] = $usuarioId;
        }
        if ($itemComCa !== null) {
            if ($itemComCa === 1) {
                $whereClauses[] = "COALESCE(i.item_epi_ca_snapshot, ep.epi_ca) IS NOT NULL AND COALESCE(i.item_epi_ca_snapshot, ep.epi_ca) != ''";
            } else {
                $whereClauses[] = "(COALESCE(i.item_epi_ca_snapshot, ep.epi_ca) IS NULL OR COALESCE(i.item_epi_ca_snapshot, ep.epi_ca) = '')";
            }
        }

        $whereSql = implode(" AND ", $whereClauses);

        // --- 1. SELEÇÃO DETALHADA DOS ITENS COM PAGINAÇÃO ---
        $valorCol = $canViewCosts ? "COALESCE(i.item_epi_valor_snapshot, ep.epi_valor)" : "NULL";
        $valorTotalCol = $canViewCosts ? "(i.item_quantidade * COALESCE(i.item_epi_valor_snapshot, ep.epi_valor))" : "NULL";

        $sqlItens = "SELECT 
                        i.item_id, i.entr_id, i.epi_id, e.entr_data_entrega, e.entr_status, e.entr_motivo,
                        COALESCE(i.item_epi_nome_snapshot, ep.epi_nome) as epi_nome,
                        COALESCE(i.item_epi_fabricante_snapshot, ep.epi_fabricante) as epi_fabricante,
                        COALESCE(i.item_epi_modelo_snapshot, ep.epi_modelo) as epi_modelo,
                        COALESCE(i.item_epi_ca_snapshot, ep.epi_ca) as epi_ca,
                        COALESCE(i.item_epi_validade_ca_snapshot, ep.epi_vencimento_ca) as epi_vencimento_ca,
                        COALESCE(i.item_epi_vida_util_snapshot, ep.epi_vida_util) as epi_vida_util,
                        $valorCol as epi_valor,
                        $valorTotalCol as valor_total,
                        i.item_quantidade, i.item_tamanho, i.item_status, i.item_data_devolucao,
                        COALESCE(i.item_motivo_entrega, e.entr_motivo) as item_motivo_entrega,
                        f.fun_nome, f.fun_cpf, f.fun_esocial, f.fun_departamento, f.fun_cargo,
                        u.usu_login,
                        i.item_devolucao_motivo, i.item_devolucao_condicao, i.item_devolucao_destino, i.item_devolucao_obs,
                        i.item_devolucao_vinculo_entrega_id, i.item_devolucao_vinculo_item_id, i.item_devolucao_tipo_operacao
                     FROM itens_entrega i
                     JOIN entrega_epis e ON i.entr_id = e.entr_id
                     LEFT JOIN funcionarios f ON e.fun_id = f.fun_id
                     LEFT JOIN epis ep ON i.epi_id = ep.epi_id
                     LEFT JOIN usuarios u ON e.usu_id = u.usu_id
                     WHERE $whereSql
                     ORDER BY $orderBy
                     LIMIT :limit OFFSET :offset";

        $stmtItens = $this->db->prepare($sqlItens);
        $stmtItens->bindValue(':limit', $limite, PDO::PARAM_INT);
        $stmtItens->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmtItens->bindValue($key, $val);
        }
        $stmtItens->execute();
        $registros = $stmtItens->fetchAll();

        // --- 2. CONTABILIZAÇÃO DO TOTAL DE REGISTROS ---
        $sqlTotal = "SELECT COUNT(*) as total_linhas
                     FROM itens_entrega i
                     JOIN entrega_epis e ON i.entr_id = e.entr_id
                     LEFT JOIN funcionarios f ON e.fun_id = f.fun_id
                     LEFT JOIN epis ep ON i.epi_id = ep.epi_id
                     WHERE $whereSql";
        $stmtTotal = $this->db->prepare($sqlTotal);
        foreach ($params as $key => $val) {
            $stmtTotal->bindValue($key, $val);
        }
        $stmtTotal->execute();
        $totalLinhas = (int)$stmtTotal->fetch()['total_linhas'];

        // --- 3. RESUMO GERENCIAL ---
        $costSum = $canViewCosts ? "SUM(i.item_quantidade * COALESCE(i.item_epi_valor_snapshot, ep.epi_valor))" : "NULL";
        $sqlResumo = "SELECT 
                        COUNT(DISTINCT e.entr_id) as total_entregas,
                        SUM(i.item_quantidade) as total_unidades,
                        COUNT(DISTINCT e.fun_id) as funcionarios_atendidos,
                        COUNT(DISTINCT i.epi_id) as epis_diferentes,
                        $costSum as custo_total,
                        SUM(CASE WHEN i.item_data_devolucao IS NOT NULL THEN i.item_quantidade ELSE 0 END) as total_devolucoes,
                        SUM(CASE WHEN i.item_devolucao_vinculo_entrega_id IS NOT NULL OR e.entr_substituicao_vinculada = 1 THEN i.item_quantidade ELSE 0 END) as total_substituicoes
                      FROM itens_entrega i
                      JOIN entrega_epis e ON i.entr_id = e.entr_id
                      LEFT JOIN funcionarios f ON e.fun_id = f.fun_id
                      LEFT JOIN epis ep ON i.epi_id = ep.epi_id
                      WHERE $whereSql";
        $stmtResumo = $this->db->prepare($sqlResumo);
        foreach ($params as $key => $val) {
            $stmtResumo->bindValue($key, $val);
        }
        $stmtResumo->execute();
        $resumo = $stmtResumo->fetch();

        // --- 4. AGRUPAMENTO POR EPI ---
        $epiCusto = $canViewCosts ? "SUM(i.item_quantidade * COALESCE(i.item_epi_valor_snapshot, ep.epi_valor))" : "NULL";
        $sqlGroupEpi = "SELECT 
                             COALESCE(i.item_epi_nome_snapshot, ep.epi_nome) as epi_nome,
                             SUM(i.item_quantidade) as quantidade,
                             COUNT(DISTINCT e.fun_id) as funcionarios,
                             $epiCusto as custo_total
                         FROM itens_entrega i
                         JOIN entrega_epis e ON i.entr_id = e.entr_id
                         LEFT JOIN funcionarios f ON e.fun_id = f.fun_id
                         LEFT JOIN epis ep ON i.epi_id = ep.epi_id
                         WHERE $whereSql
                         GROUP BY COALESCE(i.item_epi_nome_snapshot, ep.epi_nome)
                         ORDER BY quantidade DESC, epi_nome ASC";
        $stmtGroupEpi = $this->db->prepare($sqlGroupEpi);
        foreach ($params as $key => $val) {
            $stmtGroupEpi->bindValue($key, $val);
        }
        $stmtGroupEpi->execute();
        $agrupamentoEpi = $stmtGroupEpi->fetchAll();

        // --- 5. AGRUPAMENTO POR SETOR ---
        $sqlGroupSetor = "SELECT 
                             COALESCE(f.fun_departamento, 'NÃO INFORMADO') as setor,
                             COUNT(DISTINCT e.entr_id) as entregas,
                             SUM(i.item_quantidade) as unidades,
                             COUNT(DISTINCT e.fun_id) as funcionarios
                           FROM itens_entrega i
                           JOIN entrega_epis e ON i.entr_id = e.entr_id
                           LEFT JOIN funcionarios f ON e.fun_id = f.fun_id
                           WHERE $whereSql
                           GROUP BY f.fun_departamento
                           ORDER BY unidades DESC, setor ASC";
        $stmtGroupSetor = $this->db->prepare($sqlGroupSetor);
        foreach ($params as $key => $val) {
            $stmtGroupSetor->bindValue($key, $val);
        }
        $stmtGroupSetor->execute();
        $agrupamentoSetor = $stmtGroupSetor->fetchAll();

        // --- 6. AGRUPAMENTO POR MOTIVO ---
        $sqlGroupMotivo = "SELECT 
                             COALESCE(i.item_motivo_entrega, e.entr_motivo, 'OUTRO') as motivo,
                             SUM(i.item_quantidade) as quantidade
                            FROM itens_entrega i
                            JOIN entrega_epis e ON i.entr_id = e.entr_id
                            LEFT JOIN funcionarios f ON e.fun_id = f.fun_id
                            WHERE $whereSql
                            GROUP BY motivo
                            ORDER BY quantidade DESC";
        $stmtGroupMotivo = $this->db->prepare($sqlGroupMotivo);
        foreach ($params as $key => $val) {
            $stmtGroupMotivo->bindValue($key, $val);
        }
        $stmtGroupMotivo->execute();
        $agrupamentoMotivo = $stmtGroupMotivo->fetchAll();

        $totalQtdeMotivos = array_sum(array_column($agrupamentoMotivo, 'quantidade'));
        foreach ($agrupamentoMotivo as &$mot) {
            $mot['percentual'] = $totalQtdeMotivos > 0 ? round(($mot['quantidade'] / $totalQtdeMotivos) * 100, 2) : 0;
        }

        // --- 7. AGRUPAMENTO POR FUNCIONÁRIO ---
        $sqlGroupFunc = "SELECT 
                             COALESCE(f.fun_nome, 'DESCONHECIDO') as funcionario,
                             COUNT(DISTINCT i.item_id) as itens,
                             SUM(i.item_quantidade) as unidades
                           FROM itens_entrega i
                           JOIN entrega_epis e ON i.entr_id = e.entr_id
                           LEFT JOIN funcionarios f ON e.fun_id = f.fun_id
                           WHERE $whereSql
                           GROUP BY f.fun_nome
                           ORDER BY unidades DESC, funcionario ASC
                           LIMIT 15";
        $stmtGroupFunc = $this->db->prepare($sqlGroupFunc);
        foreach ($params as $key => $val) {
            $stmtGroupFunc->bindValue($key, $val);
        }
        $stmtGroupFunc->execute();
        $agrupamentoFuncionario = $stmtGroupFunc->fetchAll();

        // --- AUDITORIA ---
        $acaoAuditoria = "GERACAO_RELATORIO";
        if (isset($_GET['is_pdf']) && (int)$_GET['is_pdf'] === 1) {
            $acaoAuditoria = "EXPORTACAO_PDF";
        } else if (isset($_GET['is_print']) && (int)$_GET['is_print'] === 1) {
            $acaoAuditoria = "IMPRESSAO_RELATORIO";
        }

        $detalhesLog = "Relatório Geral de EPIs consultado para o período de " . date('d/m/Y', strtotime($dataInicial)) . " a " . date('d/m/Y', strtotime($dataFinal)) . ". Filtros: " . json_encode($_GET, JSON_UNESCAPED_UNICODE);
        Audit::log($acaoAuditoria, "Itens_Entrega", null, json_encode(['ocorrencia' => $detalhesLog], JSON_UNESCAPED_UNICODE));

        // --- RETORNO DOS DADOS ---
        Response::json(true, "Relatório Geral de EPIs gerado.", [
            'paginacao' => [
                'pagina' => $pagina,
                'limite' => $limite,
                'total_registros' => $totalLinhas,
                'total_paginas' => ceil($totalLinhas / $limite)
            ],
            'indicadores' => [
                'total_entregas' => (int)($resumo['total_entregas'] ?? 0),
                'total_unidades' => (int)($resumo['total_unidades'] ?? 0),
                'funcionarios_atendidos' => (int)($resumo['funcionarios_atendidos'] ?? 0),
                'epis_diferentes' => (int)($resumo['epis_diferentes'] ?? 0),
                'custo_total' => $canViewCosts ? (float)($resumo['custo_total'] ?? 0.0) : null,
                'total_devolucoes' => (int)($resumo['total_devolucoes'] ?? 0),
                'total_substituicoes' => (int)($resumo['total_substituicoes'] ?? 0)
            ],
            'agrupamentos' => [
                'por_epi' => $agrupamentoEpi,
                'por_setor' => $agrupamentoSetor,
                'por_motivo' => $agrupamentoMotivo,
                'por_funcionario' => $agrupamentoFuncionario
            ],
            'registros' => $registros,
            'permite_visualizar_custos' => $canViewCosts
        ]);
    }

    /**
     * GET /relatorios/epis/consumo
     * Relatório de consumo por EPI ou Todos os EPIs
     */
    public function relatorioConsumoEpis(): void {
        $tipo = $_GET['tipo_relatorio'] ?? 'ESPECIFICO';
        if ($tipo === 'TODOS') {
            $_GET['epi_id'] = null; // ignora epi_id
        } else {
            // No modo específico, exige epi_id
            if (!isset($_GET['epi_id']) || $_GET['epi_id'] === '') {
                Response::json(false, "O parâmetro epi_id é obrigatório para o relatório específico.", null, 400);
            }
        }
        $this->relatorioGeralEpis();
    }
}
