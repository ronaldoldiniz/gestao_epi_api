<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Core\Audit;
use Config\Database;
use Models\EntregaEpi;
use Models\ItemEntrega;
use Models\Funcionario;
use Models\AssinaturaEletronica;
use Models\Epi;
use Exception;
use PDO;

class EntregasController {
    private EntregaEpi $entregaModel;
    private ItemEntrega $itemModel;
    private Funcionario $funcionarioModel;
    private AssinaturaEletronica $assinaturaModel;
    private Epi $epiModel;

    public function __construct() {
        $this->entregaModel = new EntregaEpi();
        $this->itemModel = new ItemEntrega();
        $this->funcionarioModel = new Funcionario();
        $this->assinaturaModel = new AssinaturaEletronica();
        $this->epiModel = new Epi();
    }

    /**
     * GET /entregas
     */
    public function index(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR', 'RH_ADMINISTRATIVO']);
        
        $entregas = $this->entregaModel->findAll();
        foreach ($entregas as &$entrega) {
            $entrega['itens'] = $this->itemModel->findByEntregaId((int)$entrega['entr_id']);
        }
        Response::json(true, "Entregas listadas com sucesso.", $entregas);
    }

    /**
     * GET /entregas/{id}
     */
    public function show(string $id): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR', 'RH_ADMINISTRATIVO']);
        
        $entrId = (int)$id;
        $entrega = $this->entregaModel->findById($entrId);
        if (!$entrega) {
            Response::json(false, "Entrega de EPI não encontrada.", null, 404);
        }

        $itens = $this->itemModel->findByEntregaId($entrId);
        $entrega['itens'] = $itens;

        Response::json(true, "Entrega localizada com sucesso.", $entrega);
    }

    /**
     * GET /entregas/funcionario/{fun_id}
     */
    public function showByFuncionario(string $funId): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR', 'RH_ADMINISTRATIVO']);
        
        $fId = (int)$funId;
        $funcionario = $this->funcionarioModel->findById($fId, true);
        if (!$funcionario) {
            Response::json(false, "Funcionário não encontrado.", null, 404);
        }

        $entregas = $this->entregaModel->findByFuncionarioId($fId);
        // Anexa os itens de cada entrega
        foreach ($entregas as &$entrega) {
            $entrega['itens'] = $this->itemModel->findByEntregaId((int)$entrega['entr_id']);
        }

        Response::json(true, "Entregas do funcionário recuperadas com sucesso.", $entregas);
    }

    /**
     * POST /entregas (Registro de entrega em Transação com Idempotência)
     */
    public function store(): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR']);
        $input = json_decode(file_get_contents('php://input'), true);

        // 1. Receber dados
        if (!isset($input['fun_id']) || !isset($input['entr_motivo']) || !isset($input['pin']) || !isset($input['itens']) || !is_array($input['itens'])) {
            Response::json(false, "Os campos fun_id, entr_motivo, pin e itens (como array) são obrigatórios.", null, 422);
        }

        $funId = (int)$input['fun_id'];
        $motivo = trim($input['entr_motivo']);
        $pin = trim((string)$input['pin']);
        $itens = $input['itens'];
        $clientOperationId = isset($input['client_operation_id']) ? trim((string)$input['client_operation_id']) : null;

        if (strlen($pin) < 4 || strlen($pin) > 10) {
            Response::json(false, "O PIN deve possuir entre 4 e 10 caracteres.", null, 422);
        }

        if (empty($itens)) {
            Response::json(false, "É necessário informar ao menos um EPI para a entrega.", null, 422);
        }

        // Obtém o banco de dados
        $db = Database::getConnection();

        // 2. Verificação de idempotência antes de qualquer alteração ou autenticação crítica (PIN lockout)
        if ($clientOperationId) {
            $stmtOperacao = $db->prepare("SELECT * FROM operacoes_idempotentes WHERE ope_client_operation_id = :op_id");
            $stmtOperacao->execute([':op_id' => $clientOperationId]);
            $operacao = $stmtOperacao->fetch(PDO::FETCH_ASSOC);

            if ($operacao) {
                if ($operacao['ope_status'] === 'CONCLUIDA') {
                    $origResponse = json_decode($operacao['ope_resposta_json'], true);
                    Response::json(true, "A operação já havia sido concluída.", $origResponse, 200);
                } elseif ($operacao['ope_status'] === 'PROCESSANDO') {
                    Response::json(false, "Operação em processamento concorrente.", [
                        '_is_custom_payload' => true,
                        'code' => 'OPERACAO_EM_PROCESSAMENTO'
                    ], 409);
                }
            }

            // Registra inicialmente a operação com status PROCESSANDO (trava concorrência por constraint UNIQUE)
            try {
                $stmtInsertOp = $db->prepare("INSERT INTO operacoes_idempotentes (ope_client_operation_id, ope_tipo_operacao, usuario_id, fun_id, ope_status, ope_data_hora_inicio) VALUES (:op_id, 'ENTREGA_COM_DEVOLUCAO', :usu_id, :fun_id, 'PROCESSANDO', NOW())");
                $stmtInsertOp->execute([
                    ':op_id' => $clientOperationId,
                    ':usu_id' => (int)$currentUser['usu_id'],
                    ':fun_id' => $funId
                ]);
            } catch (\PDOException $e) {
                // Caso concorrência ocorra após o SELECT anterior (race condition) ou reenvio de request
                if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $stmtOperacao = $db->prepare("SELECT * FROM operacoes_idempotentes WHERE ope_client_operation_id = :op_id");
                    $stmtOperacao->execute([':op_id' => $clientOperationId]);
                    $operacao = $stmtOperacao->fetch(PDO::FETCH_ASSOC);
                    if ($operacao) {
                        if ($operacao['ope_status'] === 'CONCLUIDA') {
                            $origResponse = json_decode($operacao['ope_resposta_json'], true);
                            Response::json(true, "A operação já havia sido concluída.", $origResponse, 200);
                        } elseif ($operacao['ope_status'] === 'PROCESSANDO') {
                            Response::json(false, "Operação em processamento concorrente.", [
                                '_is_custom_payload' => true,
                                'code' => 'OPERACAO_EM_PROCESSAMENTO'
                            ], 409);
                        }
                    }
                }
                // Se cair aqui e não for tratado como idempotência existente, repassa o erro
                throw $e;
            }
        }

        // 3. Validar se funcionário existe
        $funcionario = $this->funcionarioModel->findById($funId, false);
        if (!$funcionario) {
            Response::json(false, "Funcionário não encontrado.", null, 404);
        }

        if (in_array($funcionario['fun_situacao'], ['PENDENTE_SENHA', 'INATIVO', 'AFASTADO', 'DEMITIDO']) || $funcionario['fun_situacao'] !== 'ATIVO') {
            Response::json(false, "Entrega de EPI não permitida. O funcionário está com o status '" . $funcionario['fun_situacao'] . "'.", null, 403);
        }

        // 4. Validar se assinatura eletrônica está ativa
        $assinatura = $this->assinaturaModel->findByFuncionarioId($funId);
        if (!$assinatura) {
            Response::json(false, "Assinatura eletrônica não cadastrada para este funcionário. Solicite o cadastro no RH.", null, 403);
        }

        if ($assinatura['ass_status'] === 'BLOQUEADO') {
            Response::json(false, "A assinatura eletrônica deste funcionário está BLOQUEADA. Solicite o desbloqueio no RH.", null, 403);
        }

        if ($assinatura['ass_status'] !== 'ATIVO') {
            Response::json(false, "A assinatura eletrônica deste funcionário não está ativa no momento.", null, 403);
        }

        // 5. Validar PIN/senha com password_verify()
        $configFile = dirname(__DIR__) . '/config/config.php';
        if (!file_exists($configFile)) {
            $configFile = dirname(__DIR__) . '/config/config.example.php';
        }
        $config = require $configFile;
        $maxAttempts = $config['security']['max_pin_attempts'] ?? 3;
        $secretKey = $config['app']['secret_key'];

        if (!password_verify($pin, $assinatura['ass_senha_hash'])) {
            // Incrementa falhas e bloqueia se necessário
            $attempts = $this->assinaturaModel->incrementFailAttempts((int)$assinatura['ass_id'], $maxAttempts);
            
            $detalhesLog = json_encode(['ocorrencia' => "Falha de PIN na entrega. Tentativa {$attempts} de {$maxAttempts} para o funcionário: " . $funcionario['fun_nome']], JSON_UNESCAPED_UNICODE);
            Audit::log("VALIDAÇÃO", "Entrega_EPIs", null, $detalhesLog, null, $funId, null, null, null, (int)$assinatura['ass_id']);

            if ($attempts >= $maxAttempts) {
                Response::json(false, "PIN incorreto. A assinatura eletrônica foi BLOQUEADA por excesso de erros.", null, 401);
            } else {
                $restantes = $maxAttempts - $attempts;
                Response::json(false, "PIN incorreto. Restam {$restantes} tentativa(s) antes do bloqueio.", null, 401);
            }
        }

        try {
            // 6. Iniciar transação MySQL
            $db->beginTransaction();

            // 6.1. Regra de negócio: não permite nova entrega de EPI que já está em uso pelo
            // funcionário sem devolução vinculada agendada, evitando EPIs idênticos "em uso".
            $contagemEntregue = [];
            $contagemDevolucao = [];
            foreach ($itens as $item) {
                if (!isset($item['epi_id'])) {
                    continue;
                }
                $epiIdItem = (int)$item['epi_id'];
                $contagemEntregue[$epiIdItem] = ($contagemEntregue[$epiIdItem] ?? 0) + 1;
                if (isset($item['devolucao_vinculada']['item_id_anterior'])) {
                    $contagemDevolucao[$epiIdItem] = ($contagemDevolucao[$epiIdItem] ?? 0) + 1;
                }
            }
            foreach ($contagemEntregue as $epiIdItem => $qtdEntregue) {
                $qtdDevolucao = $contagemDevolucao[$epiIdItem] ?? 0;
                $stmtEmUso = $db->prepare(
                    "SELECT COUNT(*) FROM itens_entrega i
                     JOIN entrega_epis e ON i.entr_id = e.entr_id
                     WHERE e.fun_id = :fun_id AND i.epi_id = :epi_id
                       AND i.item_status = 'ENTREGUE' AND i.item_data_devolucao IS NULL"
                );
                $stmtEmUso->execute([':fun_id' => $funId, ':epi_id' => $epiIdItem]);
                $qtdEmUso = (int)$stmtEmUso->fetchColumn();
                if (($qtdEmUso - $qtdDevolucao > 0) && ($qtdEntregue - $qtdDevolucao > 0)) {
                    throw new Exception("O EPI ID {$epiIdItem} já está em uso por este funcionário e há entrega deste item sem devolução vinculada agendada. Registre a devolução do item anterior antes de nova entrega.");
                }
            }

            // Recuperar Termo Vigente do Banco de Dados
            $stmtTermo = $db->query("SELECT termo_id, termo_versao, termo_texto_completo FROM termos_responsabilidade WHERE termo_status = 'ATIVO' LIMIT 1");
            $termoAtivo = $stmtTermo->fetch(PDO::FETCH_ASSOC);

            $termoId = $termoAtivo ? (int)$termoAtivo['termo_id'] : null;
            $termoVersao = $termoAtivo ? $termoAtivo['termo_versao'] : '1.0';
            $textoTermo = $termoAtivo ? $termoAtivo['termo_texto_completo'] : 'Declaro ter recebido os EPIs listados nesta entrega...';

            // Captura de IP e dispositivo (metadados de origem)
            $ipOrigem = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'GestaoEpi_AndroidApp';

            // Gera o hash da assinatura (passo 7) de integridade do documento
            $dadosParaHash = $funId . '|' . $assinatura['ass_id'] . '|' . time() . '|' . json_encode($itens) . '|' . $termoVersao . '|' . $ipOrigem;
            $hashAssinatura = hash_hmac('sha256', $dadosParaHash, $secretKey);

            // 7. Criar cabeçalho em Entrega_EPIs
            $entrId = $this->entregaModel->create([
                'fun_id' => $funId,
                'usu_id' => (int)$currentUser['usu_id'],
                'ass_id' => (int)$assinatura['ass_id'],
                'entr_hash_assinatura' => $hashAssinatura,
                'entr_termo_ciencia' => 'SIM',
                'entr_status' => 'FINALIZADA',
                'entr_status_sinc' => 'SINCRONIZADO',
                'entr_validacao_senha' => 'VALIDADA',
                'entr_motivo' => $motivo,
                'client_operation_id' => $clientOperationId,
                'termo_id' => $termoId,
                'termo_versao' => $termoVersao,
                'texto_termo_snapshot' => $textoTermo,
                'data_hora_aceite' => date('Y-m-d H:i:s'),
                'metodo_aceite' => 'PIN_ELETRONICO',
                'hash_termo' => hash('sha256', $textoTermo)
            ]);

            // 8. Criar itens em Itens_Entrega e validar existências de EPIs
            $hasDevolucaoVinculada = false;
            $devolucoesProcessed = [];
            $novoItemId = null;
            foreach ($itens as $item) {
                if (!isset($item['epi_id']) || !isset($item['item_quantidade']) || (int)$item['item_quantidade'] <= 0) {
                    throw new Exception("Dados de item de entrega inválidos ou quantidade zerada.");
                }

                $epiId = (int)$item['epi_id'];
                $qtd = (int)$item['item_quantidade'];

                // Verifica se EPI existe e está ativo
                $epi = $this->epiModel->findById($epiId);
                if (!$epi || $epi['epi_status'] !== 'ATIVO') {
                    throw new Exception("EPI ID {$epiId} não encontrado ou inativo no sistema.");
                }

                $novoItemId = $this->itemModel->create([
                    'entr_id' => $entrId,
                    'epi_id' => $epiId,
                    'item_quantidade' => $qtd,
                    'item_status' => 'ENTREGUE',
                    'item_numero_lote' => $item['item_numero_lote'] ?? null,
                    'item_tamanho' => $item['item_tamanho'] ?? null,
                    'item_epi_nome_snapshot' => $epi['epi_nome'],
                    'item_epi_descricao_snapshot' => $epi['epi_descricao'] ?? '',
                    'item_epi_fabricante_snapshot' => $epi['epi_fabricante'],
                    'item_epi_modelo_snapshot' => $epi['epi_modelo'] ?? '',
                    'item_epi_ca_snapshot' => $epi['epi_ca'] ?? 'NÃO REGISTRADO',
                    'item_epi_validade_ca_snapshot' => $epi['epi_vencimento_ca'] ?? null,
                    'item_epi_vida_util_snapshot' => $epi['epi_validade_uso_dias'] ?? 0,
                    'item_epi_valor_snapshot' => $epi['epi_valor'] ?? 0.00,
                    'item_epi_origem_preco_snapshot' => $epi['epi_origem_preco'] ?? 'COMPRA_DIRETA',
                    'item_epi_localizacao_snapshot' => $epi['epi_localizacao'] ?? ''
                ]);

                // --- Processamento de Devolução Vinculada Simultânea ---
                if (isset($item['devolucao_vinculada']) && is_array($item['devolucao_vinculada'])) {
                    $dev = $item['devolucao_vinculada'];
                    $itemAnteriorId = (int)$dev['item_id_anterior'];
                    $qtdDevolver = (int)$dev['quantidade_devolvida'];
                    $motivoDev = $dev['motivo'] ?? 'SUBSTITUICAO_PROGRAMADA';
                    $condicaoDev = $dev['condicao'] ?? 'DESGASTADO';
                    $destinoDev = $dev['destino'] ?? 'DESCARTE';
                    $obsDev = $dev['observacao'] ?? null;

                    // Busca item anterior para validar (SELECT FOR UPDATE garante exclusão mútua e bloqueio concorrente)
                    $stmtLock = $db->prepare("SELECT * FROM itens_entrega WHERE item_id = :id FOR UPDATE");
                    $stmtLock->execute([':id' => $itemAnteriorId]);
                    $itemAnterior = $stmtLock->fetch(PDO::FETCH_ASSOC);

                    if (!$itemAnterior) {
                        throw new Exception("Item anterior para devolução vinculada não encontrado (ID: {$itemAnteriorId}).");
                    }

                    // Verifica se o item pertence ao mesmo funcionário
                    $stmtCheckFunc = $db->prepare("SELECT fun_id FROM entrega_epis WHERE entr_id = :entr_id");
                    $stmtCheckFunc->execute([':entr_id' => $itemAnterior['entr_id']]);
                    $funItemAnterior = $stmtCheckFunc->fetchColumn();
                    if ((int)$funItemAnterior !== $funId) {
                        throw new Exception("Tentativa de devolução de EPI pertencente a outro funcionário.");
                    }

                    if ($itemAnterior['item_status'] !== 'ENTREGUE') {
                        throw new Exception("O item anterior já foi devolvido ou não possui status ativo.");
                    }

                    if ($qtdDevolver <= 0 || $qtdDevolver > (int)$itemAnterior['item_quantidade']) {
                        throw new Exception("Quantidade para devolução vinculada inválida ou superior ao saldo em uso.");
                    }

                    // Atualiza o item anterior com o vínculo e data da nova entrega (definida pelo backend)
                    $dataOperacao = date('Y-m-d H:i:s');
                    $this->itemModel->devolverVinculado($itemAnteriorId, [
                        'data_devolucao' => $dataOperacao,
                        'status' => 'DEVOLVIDO',
                        'motivo' => $motivoDev,
                        'condicao' => $condicaoDev,
                        'destino' => $destinoDev,
                        'obs' => $obsDev,
                        'vinculo_entrega_id' => $entrId,
                        'vinculo_item_id' => $novoItemId
                    ]);

                    $devolucoesProcessed[] = [
                        'devolucao_id' => $itemAnteriorId,
                        'entrega_original_id' => (int)$itemAnterior['entr_id'],
                        'item_original_id' => $itemAnteriorId,
                        'status_item_anterior' => 'DEVOLVIDO',
                        'quantidade_em_uso' => 0
                    ];
                    $hasDevolucaoVinculada = true;

                    // Grava Auditoria da devolução vinculada
                    $detalhesDev = json_encode([
                        'ocorrencia' => "Item anterior ID {$itemAnteriorId} devolvido e substituído pelo novo item ID {$novoItemId} na entrega ID {$entrId}.",
                        'motivo' => $motivoDev,
                        'condicao' => $condicaoDev,
                        'destino' => $destinoDev,
                        'quantidade' => $qtdDevolver
                    ], JSON_UNESCAPED_UNICODE);
                    Audit::log("DEVOLUCAO_VINCULADA", "itens_entrega", $itemAnteriorId, $detalhesDev, null, $funId, (int)$itemAnterior['epi_id'], (int)$itemAnterior['entr_id'], $itemAnteriorId);
                }
            }

            if ($hasDevolucaoVinculada) {
                // Atualiza o cabeçalho marcando que houve substituição vinculada
                $stmtUpdateCabecalho = $db->prepare("UPDATE entrega_epis SET entr_substituicao_vinculada = 1 WHERE entr_id = :id");
                $stmtUpdateCabecalho->execute([':id' => $entrId]);
            }

            // Atualiza a assinatura com o último uso
            $this->assinaturaModel->registerUse((int)$assinatura['ass_id']);

            // 9. Registrar logs em Log_Auditoria
            // Buscar dados extras para o Snapshot imutável
            $usuarioResponsavel = [
                'id' => (int)$currentUser['usu_id'],
                'login' => $currentUser['usu_login'],
                'nome' => $currentUser['usu_login'], // Usuários não possuem campo nome estruturado na tabela Usuarios, usamos login
                'perfil' => $currentUser['usu_perfil']
            ];

            $funcionarioDados = [
                'id' => (int)$funcionario['fun_id'],
                'nome' => $funcionario['fun_nome'],
                'matricula' => $funcionario['fun_esocial'],
                'departamento' => $funcionario['fun_departamento'],
                'cargo' => $funcionario['fun_cargo'],
                'situacao' => $funcionario['fun_situacao']
            ];

            // Montar lista de EPIs detalhados com snapshots de itens_entrega
            $itensDetalhados = [];
            $resumosDeItens = [];
            $quantidadeTotalUnidades = 0;

            // Busca os itens recém-inseridos na transação atual com seus snapshots
            $stmtItensCriados = $db->prepare("SELECT i.*, e.epi_nome, e.epi_ca, e.epi_vencimento_ca, e.epi_fabricante, e.epi_validade_uso_dias, e.epi_valor, e.epi_origem_preco, e.epi_localizacao, e.epi_exige_tamanho, e.epi_tipo_item 
                                              FROM itens_entrega i 
                                              JOIN epis e ON i.epi_id = e.epi_id 
                                              WHERE i.entr_id = :entr_id");
            $stmtItensCriados->execute([':entr_id' => $entrId]);
            $itensInseridos = $stmtItensCriados->fetchAll(PDO::FETCH_ASSOC);

            foreach ($itensInseridos as $itemIns) {
                $qtdItem = (int)$itemIns['item_quantidade'];
                $quantidadeTotalUnidades += $qtdItem;
                
                // Mapeamento amigável do motivo
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

                // Tenta calcular a validade da troca prevista em dias
                $vidaUtilDias = (int)($itemIns['epi_validade_uso_dias'] ?? 0);
                $dataPrevistaTroca = null;
                if ($vidaUtilDias > 0) {
                    $dataPrevistaTroca = date('Y-m-d', strtotime("+{$vidaUtilDias} days"));
                }

                // Dados de substituição vinculada se houver
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

                // Busca se há algum item devolvido apontando para este novo item como vínculo
                $stmtDevVinculo = $db->prepare("SELECT i.*, e.epi_nome, ent.entr_data_entrega 
                                                FROM itens_entrega i 
                                                JOIN epis e ON i.epi_id = e.epi_id 
                                                JOIN entrega_epis ent ON i.entr_id = ent.entr_id
                                                WHERE i.item_devolucao_vinculo_item_id = :item_id LIMIT 1");
                $stmtDevVinculo->execute([':item_id' => $itemIns['item_id']]);
                $devVinculoRow = $stmtDevVinculo->fetch(PDO::FETCH_ASSOC);

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
                    'nome_epi' => $itemIns['epi_nome'],
                    'descricao_complementar' => $itemIns['item_epi_descricao_snapshot'] ?? '',
                    'quantidade' => $qtdItem,
                    'unidade' => 'UNIDADE',
                    'tamanho' => $itemIns['item_tamanho'] ?? null,
                    'lote' => $itemIns['item_numero_lote'] ?? null,
                    'fabricante' => $itemIns['epi_fabricante'],
                    'ca' => $itemIns['epi_ca'] ?? 'NÃO REGISTRADO',
                    'validade_ca' => $itemIns['epi_vencimento_ca'] ?? null,
                    'vida_util_dias' => $vidaUtilDias,
                    'data_prevista_troca' => $dataPrevistaTroca,
                    'motivo_codigo' => $motivo,
                    'motivo_descricao' => $motivoDesc,
                    'destino' => 'USO_FUNCIONARIO',
                    'atualizou_estoque' => true,
                    'substituicao' => $substituicaoDados
                ];

                $resumosDeItens[] = "EPI: {$itemIns['epi_nome']}, tamanho " . ($itemIns['item_tamanho'] ?? 'Não informado') . ", lote " . ($itemIns['item_numero_lote'] ?? 'Não informado') . ", CA " . ($itemIns['epi_ca'] ?? 'Não informado');
            }

            // Descrição resumida da ocorrência para visualização em grid
            $resumosDeItensStr = implode(". ", $resumosDeItens);
            $ocorrenciaResumida = "Entrega nº {$entrId} finalizada para {$funcionario['fun_nome']}. " . count($itens) . " item e {$quantidadeTotalUnidades} unidade entregues. Motivo: " . ($motivosAmigaveis[$motivo] ?? $motivo) . ". {$resumosDeItensStr}." . ($hasDevolucaoVinculada ? " Possui substituição vinculada." : "");

            $detalhesLog = json_encode([
                'versao_log' => 2,
                'tipo_evento' => 'ENTREGA_FINALIZADA',
                'resultado' => 'SUCESSO',
                'usuario' => $usuarioResponsavel,
                'funcionario' => $funcionarioDados,
                'entrega' => [
                    'id' => $entrId,
                    'status_anterior' => 'PENDENTE_VALIDACAO',
                    'status_posterior' => 'FINALIZADA',
                    'motivo_geral' => $motivo,
                    'data_finalizacao' => date('Y-m-d H:i:s'),
                    'quantidade_itens' => count($itens),
                    'quantidade_unidades' => $quantidadeTotalUnidades,
                    'assinatura_validada' => true
                ],
                'itens' => $itensDetalhados,
                'contexto' => [
                    'ip' => $ipOrigem,
                    'user_agent' => $userAgent,
                    'origem' => 'APP_MOBILE'
                ],
                'ocorrencia' => $ocorrenciaResumida
            ], JSON_UNESCAPED_UNICODE);

            Audit::log("ENTREGA", "entrega_epis", $entrId, $detalhesLog, null, $funId, null, $entrId, null, (int)$assinatura['ass_id']);

            // 10. Confirmar transação no MySQL
            $db->commit();

            // Prepare success response payload matching goal 15 e 21
            $dataResponse = [
                'client_operation_id' => $clientOperationId,
                'entrega_id' => $entrId,
                'entr_hash_assinatura' => $hashAssinatura,
                'data_operacao' => date('Y-m-d H:i:s')
            ];

            if (!empty($devolucoesProcessed)) {
                $firstDev = $devolucoesProcessed[0];
                $dataResponse['item_entrega_id'] = $novoItemId;
                $dataResponse['devolucao_id'] = $firstDev['devolucao_id'];
                $dataResponse['entrega_original_id'] = $firstDev['entrega_original_id'];
                $dataResponse['item_original_id'] = $firstDev['item_original_id'];
                $dataResponse['status_item_anterior'] = $firstDev['status_item_anterior'];
                $dataResponse['quantidade_em_uso'] = $firstDev['quantidade_em_uso'];
            }

            if ($clientOperationId) {
                // Atualiza o status da idempotência para CONCLUIDA
                $storedData = array_merge($dataResponse, ['already_processed' => false]);
                $stmtUpdateOp = $db->prepare("UPDATE operacoes_idempotentes SET ope_status = 'CONCLUIDA', ope_entrega_id = :entr_id, ope_devolucao_id = :dev_id, ope_data_hora_conclusao = NOW(), ope_resposta_json = :json WHERE ope_client_operation_id = :op_id");
                $stmtUpdateOp->execute([
                    ':entr_id' => $entrId,
                    ':dev_id' => !empty($devolucoesProcessed) ? $devolucoesProcessed[0]['devolucao_id'] : null,
                    ':json' => json_encode(array_merge($storedData, ['already_processed' => true]), JSON_UNESCAPED_UNICODE),
                    ':op_id' => $clientOperationId
                ]);
            }

            $message = $hasDevolucaoVinculada ? "Entrega e devolução registradas com sucesso." : "Entrega registrada com sucesso.";
            Response::json(true, $message, array_merge($dataResponse, ['already_processed' => false]));
        } catch (Exception $e) {
            // Em caso de erro, desfaz transação
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

            $reference = "ENT-SUB-" . date('Ymd') . "-" . sprintf("%03d", rand(1, 999));
            error_log("[$reference] Erro ao registrar entrega de EPI: " . $e->getMessage() . " em " . $e->getFile() . " na linha " . $e->getLine());

            $statusCode = 500;
            $code = "ERRO_TRANSACAO_ENTREGA_DEVOLUCAO";
            $message = "Erro ao processar: " . $e->getMessage() . " em " . basename($e->getFile()) . ":" . $e->getLine();
            $extraData = [];

            if (strpos($e->getMessage(), 'não encontrado') !== false || strpos($e->getMessage(), 'inválido') !== false || strpos($e->getMessage(), 'obrigatório') !== false) {
                $statusCode = 422;
                $code = "ERRO_VALIDACAO";
                $message = $e->getMessage();
            } elseif (strpos($e->getMessage(), 'já foi devolvido') !== false || strpos($e->getMessage(), 'superior ao saldo') !== false) {
                if (isset($itemAnterior) && $itemAnterior['item_status'] === 'DEVOLVIDO') {
                    $statusCode = 409;
                    $code = "ITEM_JA_DEVOLVIDO_OUTRA_OPERACAO";
                    $message = "O item anterior já foi devolvido em outra operação.";
                    $extraData = [
                        'entrega_relacionada_id' => (int)($itemAnterior['item_devolucao_vinculo_entrega_id'] ?? $itemAnterior['entr_id']),
                        'devolucao_relacionada_id' => (int)($itemAnterior['item_devolucao_vinculo_item_id'] ?? $itemAnterior['item_id'])
                    ];
                } else {
                    $statusCode = 409;
                    $code = "ITEM_SEM_SALDO_ANTES_DA_OPERACAO";
                    $message = $e->getMessage();
                }
            }

            if ($clientOperationId) {
                // Atualiza o status da idempotência para FALHOU
                $stmtUpdateOpFailed = $db->prepare("UPDATE operacoes_idempotentes SET ope_status = 'FALHOU', ope_erro_referencia = :ref, ope_codigo_resultado = :code WHERE ope_client_operation_id = :op_id");
                $stmtUpdateOpFailed->execute([
                    ':ref' => $reference,
                    ':code' => $code,
                    ':op_id' => $clientOperationId
                ]);
            }

            Response::json(false, $message, array_merge([
                '_is_custom_payload' => true,
                'code' => $code,
                'reference' => $reference
            ], $extraData), $statusCode);
        }
    }

    /**
     * GET /operacoes/{client_operation_id}/status
     */
    public function checkStatus(string $clientOperationId): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR']);
        
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM operacoes_idempotentes WHERE ope_client_operation_id = :op_id LIMIT 1");
        $stmt->execute([':op_id' => $clientOperationId]);
        $op = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$op) {
            Response::json(false, "Operação não encontrada.", null, 404);
        }

        if ($op['ope_status'] === 'CONCLUIDA') {
            $respData = json_decode($op['ope_resposta_json'], true);
            Response::json(true, "Operação concluída.", [
                '_is_custom_payload' => true,
                'status' => 'CONCLUIDA',
                'data' => $respData
            ]);
        } elseif ($op['ope_status'] === 'FALHOU') {
            Response::json(true, "Operação falhou.", [
                '_is_custom_payload' => true,
                'status' => 'FALHOU',
                'code' => $op['ope_codigo_resultado'] ?? 'ERRO_DESCONHECIDO',
                'message' => 'A transação falhou no servidor.',
                'reference' => $op['ope_erro_referencia'] ?? 'N/A'
            ]);
        } else {
            Response::json(true, "Operação em processamento.", [
                '_is_custom_payload' => true,
                'status' => 'PROCESSANDO'
            ]);
        }
    }

    /**
     * POST /entregas/item/{id}/corrigir
     * Correção administrativa de dados históricos de item entregue
     */
    public function corrigirItem(string $id): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR']);
        $itemId = (int)$id;

        $item = $this->itemModel->findById($itemId);
        if (!$item) {
            Response::json(false, "Item da entrega não localizado.", null, 404);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['justificativa']) || trim($input['justificativa']) === '') {
            Response::json(false, "É obrigatório informar uma justificativa para a correção administrativa.", null, 422);
        }
        if (!isset($input['correcoes']) || !is_array($input['correcoes'])) {
            Response::json(false, "Informe os campos e valores a serem corrigidos no objeto 'correcoes'.", null, 422);
        }

        $justificativa = trim($input['justificativa']);
        $correcoes = $input['correcoes'];

        // Mapeia chaves enviadas aos campos snapshot permitidos
        $allowedFields = [
            'item_epi_nome_snapshot' => 'nome',
            'item_epi_descricao_snapshot' => 'descrição',
            'item_epi_fabricante_snapshot' => 'fabricante',
            'item_epi_modelo_snapshot' => 'modelo',
            'item_epi_ca_snapshot' => 'CA',
            'item_epi_validade_ca_snapshot' => 'validade do CA',
            'item_epi_vida_util_snapshot' => 'vida útil',
            'item_epi_valor_snapshot' => 'valor',
            'item_epi_origem_preco_snapshot' => 'origem do preço',
            'item_epi_localizacao_snapshot' => 'localização'
        ];

        $updates = [];
        $logChanges = [];

        foreach ($correcoes as $key => $val) {
            if (array_key_exists($key, $allowedFields)) {
                $updates[$key] = $val;
                $originalVal = $item[$key] ?? 'NÃO REGISTRADO';
                $logChanges[] = [
                    'campo' => $key,
                    'nome_campo' => $allowedFields[$key],
                    'valor_anterior' => $originalVal,
                    'valor_novo' => $val
                ];
            }
        }

        if (empty($updates)) {
            Response::json(false, "Nenhum campo válido de snapshot foi enviado para correção.", null, 422);
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->itemModel->updateHistoricalData($itemId, $updates);

            foreach ($logChanges as $change) {
                $detalhesLog = json_encode([
                    'ocorrencia' => "Correção administrativa de item de entrega.",
                    'campo' => $change['campo'],
                    'campo_rotulo' => $change['nome_campo'],
                    'valor_anterior' => $change['valor_anterior'],
                    'valor_novo' => $change['valor_novo'],
                    'justificativa' => $justificativa
                ], JSON_UNESCAPED_UNICODE);

                Audit::log(
                    "CORREÇÃO", 
                    "Itens_Entrega", 
                    $itemId, 
                    $detalhesLog, 
                    null, 
                    null, 
                    null, 
                    (int)$item['entr_id'], 
                    $itemId
                );
            }

            $db->commit();
            Response::json(true, "Dados históricos do item de entrega corrigidos com sucesso.");
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Response::json(false, "Erro ao realizar a correção administrativa: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /entregas/{id}/cancelar
     */
    public function cancelar(string $id): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST']);
        $entrId = (int)$id;

        $entrega = $this->entregaModel->findById($entrId);
        if (!$entrega) {
            Response::json(false, "Entrega não localizada.", null, 404);
        }

        if ($entrega['entr_status'] === 'CANCELADA') {
            Response::json(false, "Esta entrega já foi cancelada anteriormente.", null, 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['motivo']) || trim($input['motivo']) === '') {
            Response::json(false, "Informe o motivo do cancelamento da entrega.", null, 422);
        }

        $motivo = trim($input['motivo']);
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // Cancela entrega
            $this->entregaModel->cancel($entrId, $motivo, (int)$currentUser['usu_id']);

            // Altera status de todos os itens para 'CANCELADO'
            $this->itemModel->cancelByEntregaId($entrId);

            $detalhesLog = json_encode(['ocorrencia' => "Termo de entrega cancelado. Motivo: " . $motivo], JSON_UNESCAPED_UNICODE);
            Audit::log("CANCELAMENTO", "Entrega_EPIs", $entrId, $detalhesLog, null, (int)$entrega['fun_id'], null, $entrId);

            $db->commit();
            Response::json(true, "Entrega cancelada com sucesso.");
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Response::json(false, "Falha ao cancelar entrega: " . $e->getMessage(), null, 500);
        }
    }
}
