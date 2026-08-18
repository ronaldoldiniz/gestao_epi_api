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
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        
        $entregas = $this->entregaModel->findAll();
        Response::json(true, "Entregas listadas com sucesso.", $entregas);
    }

    /**
     * GET /entregas/{id}
     */
    public function show(string $id): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        
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
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        
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
     * POST /entregas (Registro de entrega em Transação)
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
        $clientOperationId = $input['client_operation_id'] ?? null;

        if (empty($itens)) {
            Response::json(false, "É necessário informar ao menos um EPI para a entrega.", null, 422);
        }

        // 2. Validar se funcionário existe
        $funcionario = $this->funcionarioModel->findById($funId, false);
        if (!$funcionario) {
            Response::json(false, "Funcionário não encontrado.", null, 404);
        }

        if (in_array($funcionario['fun_situacao'], ['PENDENTE_SENHA', 'INATIVO', 'AFASTADO', 'DEMITIDO']) || $funcionario['fun_situacao'] !== 'ATIVO') {
            Response::json(false, "Entrega de EPI não permitida. O funcionário está com o status '" . $funcionario['fun_situacao'] . "'.", null, 403);
        }

        // 3. Validar se assinatura eletrônica está ativa
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

        // 4. Validar PIN/senha com password_verify()
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
            
            Audit::log("Falha de PIN na entrega", "Entrega_EPIs", null, "Tentativa {$attempts} de {$maxAttempts} para o funcionário: " . $funcionario['fun_nome'], null, $funId, null, null, null, (int)$assinatura['ass_id']);

            if ($attempts >= $maxAttempts) {
                Response::json(false, "PIN incorreto. A assinatura eletrônica foi BLOQUEADA por excesso de erros.", null, 401);
            } else {
                $restantes = $maxAttempts - $attempts;
                Response::json(false, "PIN incorreto. Restam {$restantes} tentativa(s) antes do bloqueio.", null, 401);
            }
        }

        // Obtém o banco de dados para iniciar a transação unificada
        $db = Database::getConnection();

        try {
            // 5. Iniciar transação MySQL
            $db->beginTransaction();

            // Gera o hash da assinatura (passo 7) de integridade do documento
            $dadosParaHash = $funId . '|' . $assinatura['ass_id'] . '|' . time() . '|' . json_encode($itens);
            $hashAssinatura = hash_hmac('sha256', $dadosParaHash, $secretKey);

            // 6. Criar cabeçalho em Entrega_EPIs
            // Passos 8, 9, 10: Termo ciencia = SIM, Validação senha = VALIDADA, Status = FINALIZADA
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
                'client_operation_id' => $clientOperationId
            ]);

            // 11. Criar itens em Itens_Entrega e validar existências de EPIs
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
                    'item_motivo_entrega' => $item['item_motivo_entrega'] ?? null
                ]);

                // Processa devolução vinculada se houver
                if (isset($item['devolucao_vinculada']) && is_array($item['devolucao_vinculada'])) {
                    $dev = $item['devolucao_vinculada'];
                    $itemIdAnterior = (int)($dev['item_id_anterior'] ?? 0);

                    if ($itemIdAnterior > 0) {
                        $itemAnterior = $this->itemModel->findById($itemIdAnterior);
                        if ($itemAnterior && $itemAnterior['item_status'] === 'ENTREGUE') {
                            $statusDevolucao = ($dev['condicao'] === 'EXTRAVIADO') ? 'EXTRAVIADO' : 'DEVOLVIDO';
                            $this->itemModel->devolver($itemIdAnterior, $statusDevolucao, [
                                'motivo' => $dev['motivo'] ?? null,
                                'condicao' => $dev['condicao'] ?? null,
                                'destino' => $dev['destino'] ?? null,
                                'observacao' => $dev['observacao'] ?? null,
                                'vinculo_entrega_id' => $entrId,
                                'vinculo_item_id' => $novoItemId,
                                'tipo_operacao' => 'DEVOLUCAO_VINCULADA_A_NOVA_ENTREGA'
                            ]);

                            Audit::log(
                                "Registrou devolução vinculada a nova entrega",
                                "Itens_Entrega",
                                $itemIdAnterior,
                                "Item anterior #{$itemIdAnterior} (EPI ID {$itemAnterior['epi_id']}) devolvido via entrega vinculada #{$entrId}. Status: {$statusDevolucao}",
                                null,
                                $funId,
                                (int)$itemAnterior['epi_id'],
                                $entrId,
                                $itemIdAnterior,
                                (int)$assinatura['ass_id']
                            );
                        }
                    }
                }
            }

            // Atualiza a assinatura com o último uso
            $this->assinaturaModel->registerUse((int)$assinatura['ass_id']);

            // 11. Registrar logs em Log_Auditoria
            Audit::log("Registrou entrega de EPIs", "Entrega_EPIs", $entrId, "Entrega FINALIZADA para o funcionário: " . $funcionario['fun_nome'] . ". Itens entregues: " . count($itens), null, $funId, null, $entrId, null, (int)$assinatura['ass_id']);

            // 12. Confirmar transação
            $db->commit();

            Response::json(true, "Entrega registrada com sucesso.", [
                'entr_id' => $entrId,
                'entr_hash_assinatura' => $hashAssinatura
            ]);
        } catch (Exception $e) {
            // 13. Em caso de erro, desfaz transação
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Response::json(false, "Erro ao registrar a entrega de EPI: " . $e->getMessage(), null, 500);
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

            Audit::log("Cancelou termo de entrega de EPIs", "Entrega_EPIs", $entrId, "Motivo: " . $motivo, null, (int)$entrega['fun_id'], null, $entrId);

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
