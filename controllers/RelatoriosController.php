<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
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
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR']);
        
        $sql = "SELECT e.entr_id, f.fun_nome, u.usu_login, e.entr_data_entrega, e.entr_status, e.entr_motivo,
                       (SELECT COUNT(*) FROM Itens_Entrega i WHERE i.entr_id = e.entr_id) as total_itens
                FROM Entrega_EPIs e
                JOIN Funcionarios f ON e.fun_id = f.fun_id
                JOIN Usuarios u ON e.usu_id = u.usu_id
                ORDER BY e.entr_data_entrega DESC";
        
        $stmt = $this->db->query($sql);
        $relatorio = $stmt->fetchAll();

        Response::json(true, "Relatório de entregas gerado com sucesso.", $relatorio);
    }

    /**
     * GET /relatorios/entregas/funcionario/{fun_id}
     */
    public function entregasPorFuncionario(string $funId): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR']);
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
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR']);
        
        $sql = "SELECT * FROM EPIs WHERE epi_vencimento_ca < CURDATE() OR epi_status = 'VENCIDO'";
        $stmt = $this->db->query($sql);
        $vencidos = $stmt->fetchAll();

        Response::json(true, "Relatório de EPIs vencidos gerado.", $vencidos);
    }

    /**
     * GET /relatorios/ca-vencidos
     * Relatório focado estritamente no vencimento do C.A. da tabela de EPIs
     */
    public function caVencidos(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR']);
        
        $sql = "SELECT epi_id, epi_nome, epi_ca, epi_vencimento_ca, epi_fabricante, epi_status 
                FROM EPIs 
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
                    SUM(i.item_quantidade * ep.epi_valor) AS custo_total,
                    COUNT(DISTINCT e.entr_id) as total_entregas,
                    SUM(i.item_quantidade) as total_itens_entregues
                FROM Itens_Entrega i
                JOIN Entrega_EPIs e ON i.entr_id = e.entr_id
                JOIN EPIs ep ON i.epi_id = ep.epi_id
                WHERE e.entr_status = 'FINALIZADA'
                GROUP BY DATE_FORMAT(e.entr_data_entrega, '%Y-%m')
                ORDER BY mes DESC";
        
        $stmt = $this->db->query($sql);
        $custos = $stmt->fetchAll();

        Response::json(true, "Relatório de custo mensal gerado.", $custos);
    }

    /**
     * GET /relatorios/epis/consumo
     * Relatório de consumo de EPIs (específico por EPI ou todos)
     */
    public function consumoEpis(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);

        $params = $_GET;
        $tipoRelatorio = $params['tipo_relatorio'] ?? 'TODOS';
        $dataInicial = $params['data_inicial'] ?? null;
        $dataFinal = $params['data_final'] ?? null;
        $funcionarioId = !empty($params['funcionario_id']) ? (int)$params['funcionario_id'] : null;
        $departamento = $params['departamento'] ?? null;
        $cargo = $params['cargo'] ?? null;
        $epiId = !empty($params['epi_id']) ? (int)$params['epi_id'] : null;
        $categoria = $params['categoria'] ?? null;
        $motivo = $params['motivo'] ?? null;
        $usuarioId = !empty($params['usuario_id']) ? (int)$params['usuario_id'] : null;
        $itemComCa = !empty($params['item_com_ca']) ? (int)$params['item_com_ca'] : null;
        $statusEntrega = $params['status_entrega'] ?? 'FINALIZADA';
        $pagina = max(1, (int)($params['pagina'] ?? 1));
        $limite = max(1, min(10000, (int)($params['limite'] ?? 25)));
        $ordenacao = $params['ordenacao'] ?? 'data_desc';
        $currentUser = Auth::getCurrentUser();
        $perfil = $currentUser['usu_perfil'] ?? '';
        $permiteCustos = in_array($perfil, ['ADMINISTRADOR', 'GESTOR']);

        $where = ["e.entr_status = :status_entrega"];
        $bindings = [':status_entrega' => $statusEntrega];

        if ($dataInicial) {
            $where[] = "e.entr_data_entrega >= :data_inicial";
            $bindings[':data_inicial'] = $dataInicial;
        }
        if ($dataFinal) {
            $where[] = "e.entr_data_entrega <= :data_final";
            $bindings[':data_final'] = $dataFinal;
        }
        if ($funcionarioId !== null) {
            $where[] = "e.fun_id = :funcionario_id";
            $bindings[':funcionario_id'] = $funcionarioId;
        }
        if ($departamento) {
            $where[] = "f.fun_departamento = :departamento";
            $bindings[':departamento'] = $departamento;
        }
        if ($cargo) {
            $where[] = "f.fun_cargo = :cargo";
            $bindings[':cargo'] = $cargo;
        }
        if ($epiId !== null) {
            $where[] = "i.epi_id = :epi_id";
            $bindings[':epi_id'] = $epiId;
        }
        if ($motivo) {
            $where[] = "i.item_motivo_entrega = :motivo";
            $bindings[':motivo'] = $motivo;
        }
        if ($usuarioId !== null) {
            $where[] = "e.usu_id = :usuario_id";
            $bindings[':usuario_id'] = $usuarioId;
        }
        if ($itemComCa !== null && $itemComCa === 1) {
            $where[] = "ep.epi_ca IS NOT NULL AND ep.epi_ca != ''";
        }

        $whereSql = implode(' AND ', $where);

        $allowedSorts = [
            'data_desc' => 'e.entr_data_entrega DESC',
            'data_asc' => 'e.entr_data_entrega ASC',
            'funcionario' => 'f.fun_nome ASC',
            'epi' => 'ep.epi_nome ASC',
        ];
        $orderSql = $allowedSorts[$ordenacao] ?? 'e.entr_data_entrega DESC';

        $baseQuery = "FROM Itens_Entrega i
                      JOIN Entrega_EPIs e ON i.entr_id = e.entr_id
                      JOIN EPIs ep ON i.epi_id = ep.epi_id
                      JOIN Funcionarios f ON e.fun_id = f.fun_id
                      JOIN Usuarios u ON e.usu_id = u.usu_id
                      WHERE {$whereSql}";

        $countSql = "SELECT COUNT(*) as total {$baseQuery}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($bindings);
        $totalRegistros = (int)$countStmt->fetchColumn();
        $totalPaginas = max(1, (int)ceil($totalRegistros / $limite));
        $offset = ($pagina - 1) * $limite;

        $registrosSql = "SELECT i.item_id, e.entr_id, e.epi_id,
                            e.entr_data_entrega, e.entr_status, e.entr_motivo,
                            ep.epi_nome, ep.epi_fabricante, NULL as epi_modelo,
                            ep.epi_ca, ep.epi_vencimento_ca, ep.epi_validade_uso_dias as epi_vida_util,
                            ep.epi_valor, (i.item_quantidade * ep.epi_valor) as valor_total,
                            i.item_quantidade, i.item_tamanho, i.item_status, i.item_data_devolucao,
                            i.item_motivo_entrega,
                            f.fun_nome, f.fun_cpf, f.fun_esocial, f.fun_departamento, f.fun_cargo,
                            u.usu_login,
                            i.item_devolucao_motivo, i.item_devolucao_condicao,
                            i.item_devolucao_destino, i.item_devolucao_obs,
                            i.item_devolucao_vinculo_entrega_id, i.item_devolucao_vinculo_item_id,
                            i.item_devolucao_tipo_operacao
                         {$baseQuery}
                         ORDER BY {$orderSql}
                         LIMIT :limite OFFSET :offset";

        $regStmt = $this->db->prepare($registrosSql);
        foreach ($bindings as $key => $val) {
            $regStmt->bindValue($key, $val);
        }
        $regStmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $regStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $regStmt->execute();
        $registros = $regStmt->fetchAll();

        $indicadoresSql = "SELECT
                            COUNT(DISTINCT e.entr_id) as total_entregas,
                            COALESCE(SUM(i.item_quantidade), 0) as total_unidades,
                            COUNT(DISTINCT e.fun_id) as funcionarios_atendidos,
                            COUNT(DISTINCT i.epi_id) as epis_diferentes,
                            COALESCE(SUM(i.item_quantidade * ep.epi_valor), 0) as custo_total,
                            COALESCE(SUM(CASE WHEN i.item_data_devolucao IS NOT NULL THEN i.item_quantidade ELSE 0 END), 0) as total_devolucoes,
                            COALESCE(SUM(CASE WHEN i.item_devolucao_tipo_operacao = 'SUBSTITUICAO' THEN i.item_quantidade ELSE 0 END), 0) as total_substituicoes
                         {$baseQuery}";
        $indStmt = $this->db->prepare($indicadoresSql);
        foreach ($bindings as $key => $val) {
            $indStmt->bindValue($key, $val);
        }
        $indStmt->execute();
        $indicadores = $indStmt->fetch();

        $porEpiSql = "SELECT ep.epi_nome as epi_nome,
                            COALESCE(SUM(i.item_quantidade), 0) as quantidade,
                            COUNT(DISTINCT e.fun_id) as funcionarios,
                            COALESCE(SUM(i.item_quantidade * ep.epi_valor), 0) as custo_total
                      {$baseQuery}
                      GROUP BY ep.epi_id, ep.epi_nome
                      ORDER BY quantidade DESC";
        $peStmt = $this->db->prepare($porEpiSql);
        foreach ($bindings as $key => $val) {
            $peStmt->bindValue($key, $val);
        }
        $peStmt->execute();
        $porEpi = $peStmt->fetchAll();

        $porSetorSql = "SELECT f.fun_departamento as setor,
                            COUNT(DISTINCT e.entr_id) as entregas,
                            COALESCE(SUM(i.item_quantidade), 0) as unidades,
                            COUNT(DISTINCT e.fun_id) as funcionarios
                       {$baseQuery}
                       GROUP BY f.fun_departamento
                       ORDER BY unidades DESC";
        $psStmt = $this->db->prepare($porSetorSql);
        foreach ($bindings as $key => $val) {
            $psStmt->bindValue($key, $val);
        }
        $psStmt->execute();
        $porSetor = $psStmt->fetchAll();

        $porMotivoSql = "SELECT COALESCE(i.item_motivo_entrega, 'Não informado') as motivo,
                            COALESCE(SUM(i.item_quantidade), 0) as quantidade,
                            0 as percentual
                         {$baseQuery}
                         GROUP BY i.item_motivo_entrega
                         ORDER BY quantidade DESC";
        $pmStmt = $this->db->prepare($porMotivoSql);
        foreach ($bindings as $key => $val) {
            $pmStmt->bindValue($key, $val);
        }
        $pmStmt->execute();
        $porMotivo = $pmStmt->fetchAll();
        $totalUnidadesMotivo = array_sum(array_column($porMotivo, 'quantidade'));
        foreach ($porMotivo as &$pm) {
            $pm['percentual'] = $totalUnidadesMotivo > 0
                ? round((float)$pm['quantidade'] * 100.0 / $totalUnidadesMotivo, 1)
                : 0.0;
        }
        unset($pm);

        $porFunSql = "SELECT f.fun_nome as funcionario,
                         COUNT(DISTINCT i.item_id) as itens,
                         COALESCE(SUM(i.item_quantidade), 0) as unidades
                      {$baseQuery}
                      GROUP BY f.fun_id, f.fun_nome
                      ORDER BY unidades DESC";
        $pfStmt = $this->db->prepare($porFunSql);
        foreach ($bindings as $key => $val) {
            $pfStmt->bindValue($key, $val);
        }
        $pfStmt->execute();
        $porFuncionario = $pfStmt->fetchAll();

        $data = [
            'paginacao' => [
                'pagina' => $pagina,
                'limite' => $limite,
                'total_registros' => $totalRegistros,
                'total_paginas' => $totalPaginas,
            ],
            'indicadores' => [
                'total_entregas' => (int)($indicadores['total_entregas'] ?? 0),
                'total_unidades' => (int)($indicadores['total_unidades'] ?? 0),
                'funcionarios_atendidos' => (int)($indicadores['funcionarios_atendidos'] ?? 0),
                'epis_diferentes' => (int)($indicadores['epis_diferentes'] ?? 0),
                'custo_total' => $permiteCustos ? (float)($indicadores['custo_total'] ?? 0) : null,
                'total_devolucoes' => (int)($indicadores['total_devolucoes'] ?? 0),
                'total_substituicoes' => (int)($indicadores['total_substituicoes'] ?? 0),
            ],
            'agrupamentos' => [
                'por_epi' => $porEpi,
                'por_setor' => $porSetor,
                'por_motivo' => $porMotivo,
                'por_funcionario' => $porFuncionario,
            ],
            'registros' => $registros,
            'permite_visualizar_custos' => $permiteCustos,
        ];

        Response::json(true, "Relatório de consumo de EPIs gerado com sucesso.", $data);
    }

}
