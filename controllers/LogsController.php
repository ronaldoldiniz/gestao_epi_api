<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Models\LogAuditoria;

class LogsController {
    private LogAuditoria $logModel;

    public function __construct() {
        $this->logModel = new LogAuditoria();
    }

    /**
     * GET /logs
     */
    public function index(): void {
        $currentUser = Auth::requireAuth();
        if ($currentUser['usu_perfil'] !== 'ADMINISTRADOR') {
            Response::json(false, "Acesso negado. Esta funcionalidade é exclusiva do Administrador do Sistema.", null, 403);
            return;
        }

        $filters = [
            'usuario'       => $_GET['usuario'] ?? null,
            'data_inicio'   => $_GET['data_inicio'] ?? null, // YYYY-MM-DD
            'data_fim'      => $_GET['data_fim'] ?? null,    // YYYY-MM-DD
            'acao'          => $_GET['acao'] ?? null,
            'entidade'      => $_GET['entidade'] ?? null,
            'palavra_chave' => $_GET['palavra_chave'] ?? null
        ];

        $logs = $this->logModel->findFiltered($filters);
        Response::json(true, "Logs de auditoria listados com sucesso.", $logs);
    }

    /**
     * GET /logs/{id}
     */
    public function show(string $id): void {
        $currentUser = Auth::requireAuth();
        if ($currentUser['usu_perfil'] !== 'ADMINISTRADOR') {
            Response::json(false, "Acesso negado. Esta funcionalidade é exclusiva do Administrador do Sistema.", null, 403);
            return;
        }

        $logId = (int)$id;
        $log = $this->logModel->findById($logId);
        if (!$log) {
            Response::json(false, "Log de auditoria não encontrado.", null, 404);
            return;
        }

        // Tenta decodificar o JSON existente
        $detalhesRaw = $log['log_detalhes'] ?? '';
        $detalhesJson = json_decode($detalhesRaw, true);

        // Se for uma entrega e não possuir snapshot estruturado versão >= 2, reconstrói a partir do banco
        if ($log['log_tabela'] === 'entrega_epis' && (!is_array($detalhesJson) || !isset($detalhesJson['versao_log']) || $detalhesJson['versao_log'] < 2)) {
            $db = \Config\Database::getConnection();
            $registroId = (int)$log['log_registro_id'];

            // 1. Busca a entrega
            $stmtEntrega = $db->prepare("SELECT e.*, u.usu_login, u.usu_perfil, f.fun_id, f.fun_nome, f.fun_esocial, f.fun_departamento, f.fun_cargo, f.fun_situacao
                                         FROM entrega_epis e
                                         LEFT JOIN usuarios u ON e.usu_id = u.usu_id
                                         LEFT JOIN funcionarios f ON e.fun_id = f.fun_id
                                         WHERE e.entr_id = :id LIMIT 1");
            $stmtEntrega->execute([':id' => $registroId]);
            $entregaRow = $stmtEntrega->fetch(\PDO::FETCH_ASSOC);

            if ($entregaRow) {
                // 2. Busca os itens da entrega
                $stmtItens = $db->prepare("SELECT i.*, e.epi_nome, e.epi_ca, e.epi_vencimento_ca, e.epi_fabricante, e.epi_validade_uso_dias, e.epi_valor, e.epi_origem_preco, e.epi_localizacao, e.epi_exige_tamanho, e.epi_tipo_item 
                                           FROM itens_entrega i 
                                           JOIN epis e ON i.epi_id = e.epi_id 
                                           WHERE i.entr_id = :entr_id");
                $stmtItens->execute([':entr_id' => $registroId]);
                $itensRows = $stmtItens->fetchAll(\PDO::FETCH_ASSOC);

                $itensDetalhados = [];
                $quantidadeTotalUnidades = 0;

                foreach ($itensRows as $itemIns) {
                    $qtdItem = (int)$itemIns['item_quantidade'];
                    $quantidadeTotalUnidades += $qtdItem;

                    $motivo = $entregaRow['entr_motivo'] ?? 'OUTROS';
                    $motivosAmigaveis = [
                        'ADMISSAO' => 'Admissão',
                        'SUBSTITUICAO' => 'Substituição',
                        'VENCIMENTO' => 'Vencimento da vida útil',
                        'PERDA' => 'Perda',
                        'DANO' => 'Dano',
                        'TROCA_FUNCAO' => 'Troca de função',
                        'OUTROS' => 'Outros'
                    ];
                    $motivoDesc = $motivosAmigaveis[$motivo] ?? $motivo;

                    $vidaUtilDias = (int)($itemIns['epi_validade_uso_dias'] ?? 0);
                    $dataPrevistaTroca = $itemIns['item_data_devolucao'] ?? null;
                    if ($vidaUtilDias > 0 && empty($dataPrevistaTroca)) {
                        $dataPrevistaTroca = date('Y-m-d', strtotime($entregaRow['entr_data_entrega'] . " +{$vidaUtilDias} days"));
                    }

                    // Busca se há algum item devolvido que aponte para este novo item como vínculo (substituição vinculada)
                    $substituicaoDados = [
                        'possui_vinculo' => false,
                        'id_entrega_anterior' => null,
                        'id_item_anterior' => null,
                        'nome_epi_anterior' => null,
                        'data_entrega_anterior' => null,
                        'motivo_substituicao' => null,
                        'motivo_devolucao' => null,
                        'data_devolucao' => null,
                        'destino_item_devolvido' => null
                    ];

                    $stmtDevVinculo = $db->prepare("SELECT i.*, e.epi_nome, ent.entr_data_entrega 
                                                    FROM itens_entrega i 
                                                    JOIN epis e ON i.epi_id = e.epi_id 
                                                    JOIN entrega_epis ent ON i.entr_id = ent.entr_id
                                                    WHERE i.item_devolucao_vinculo_item_id = :item_id LIMIT 1");
                    $stmtDevVinculo->execute([':item_id' => $itemIns['item_id']]);
                    $devVinculoRow = $stmtDevVinculo->fetch(\PDO::FETCH_ASSOC);

                    if ($devVinculoRow) {
                        $substituicaoDados = [
                            'possui_vinculo' => true,
                            'id_entrega_anterior' => (int)$devVinculoRow['entr_id'],
                            'id_item_anterior' => (int)$devVinculoRow['item_id'],
                            'nome_epi_anterior' => $devVinculoRow['epi_nome'],
                            'data_entrega_anterior' => substr($devVinculoRow['entr_data_entrega'], 0, 10),
                            'motivo_substituicao' => $motivo,
                            'motivo_devolucao' => $devVinculoRow['item_devolucao_motivo'],
                            'data_devolucao' => substr($devVinculoRow['item_data_devolucao'], 0, 10),
                            'destino_item_devolvido' => $devVinculoRow['item_devolucao_destino']
                        ];
                    }

                    $itensDetalhados[] = [
                        'id_item_entrega' => (int)$itemIns['item_id'],
                        'id_epi' => (int)$itemIns['epi_id'],
                        'nome_epi' => $itemIns['item_epi_nome_snapshot'] ?: $itemIns['epi_nome'],
                        'descricao_complementar' => $itemIns['item_epi_descricao_snapshot'] ?? '',
                        'quantidade' => $qtdItem,
                        'unidade' => 'UNIDADE',
                        'tamanho' => $itemIns['item_tamanho'] ?? null,
                        'lote' => $itemIns['item_numero_lote'] ?? null,
                        'fabricante' => $itemIns['item_epi_fabricante_snapshot'] ?: $itemIns['epi_fabricante'],
                        'ca' => $itemIns['item_epi_ca_snapshot'] ?: ($itemIns['epi_ca'] ?? 'NÃO REGISTRADO'),
                        'validade_ca' => $itemIns['item_epi_validade_ca_snapshot'] ?: ($itemIns['epi_vencimento_ca'] ?? null),
                        'vida_util_dias' => $vidaUtilDias,
                        'data_prevista_troca' => $dataPrevistaTroca,
                        'motivo_codigo' => $motivo,
                        'motivo_descricao' => $motivoDesc,
                        'destino' => 'USO_FUNCIONARIO',
                        'atualizou_estoque' => true,
                        'substituicao' => $substituicaoDados
                    ];
                }

                $usuarioResponsavel = [
                    'id' => (int)$entregaRow['usu_id'],
                    'login' => $entregaRow['usu_login'],
                    'nome' => $entregaRow['usu_login'],
                    'perfil' => $entregaRow['usu_perfil']
                ];

                $funcionarioDados = [
                    'id' => (int)$entregaRow['fun_id'],
                    'nome' => $entregaRow['fun_nome'],
                    'matricula' => $entregaRow['fun_esocial'],
                    'departamento' => $entregaRow['fun_departamento'],
                    'cargo' => $entregaRow['fun_cargo'],
                    'situacao' => $entregaRow['fun_situacao']
                ];

                $reconstructed = [
                    'versao_log' => 2,
                    'origem_detalhes' => 'DADOS_OPERACIONAIS_ATUAIS',
                    'tipo_evento' => 'ENTREGA_FINALIZADA',
                    'resultado' => 'SUCESSO',
                    'usuario' => $usuarioResponsavel,
                    'funcionario' => $funcionarioDados,
                    'entrega' => [
                        'id' => $registroId,
                        'status_anterior' => 'PENDENTE_VALIDACAO',
                        'status_posterior' => $entregaRow['entr_status'],
                        'motivo_geral' => $entregaRow['entr_motivo'],
                        'data_finalizacao' => $entregaRow['entr_data_entrega'],
                        'quantidade_itens' => count($itensDetalhados),
                        'quantidade_unidades' => $quantidadeTotalUnidades,
                        'assinatura_validada' => $entregaRow['entr_validacao_senha'] === 'VALIDADA'
                    ],
                    'itens' => $itensDetalhados,
                    'ocorrencia' => ($detalhesJson['ocorrencia'] ?? '') ?: ("Entrega nº {$registroId} finalizada para " . $entregaRow['fun_nome'])
                ];

                $log['log_detalhes'] = json_encode($reconstructed, JSON_UNESCAPED_UNICODE);
            }
        }

        Response::json(true, "Log de auditoria localizado com sucesso.", $log);
    }

    /**
     * POST /logs/registrar-exportacao
     */
    public function registrarExportacao(): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR']);
        $input = json_decode(file_get_contents('php://input'), true);
        
        $quantidade = (int)($input['quantidade'] ?? 0);
        $filtros = $input['filtros'] ?? 'Nenhum';
        
        $ocorrencia = "Relatório de auditoria exportado em PDF com {$quantidade} registros.";
        $detalhesJson = json_encode([
            'ocorrencia' => $ocorrencia,
            'filtros' => $filtros,
            'formato' => 'PDF'
        ], JSON_UNESCAPED_UNICODE);
        
        \Core\Audit::log('EXPORTAÇÃO', 'Log_Auditoria', null, $detalhesJson, (int)$currentUser['usu_id']);
        Response::json(true, "Exportação registrada com sucesso.");
    }
}
