<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Core\Audit;
use Config\Database;
use Models\ItemEntrega;
use Models\EntregaEpi;
use Models\Funcionario;
use Exception;

class DevolucoesController {
    private ItemEntrega $itemModel;
    private EntregaEpi $entregaModel;
    private Funcionario $funcionarioModel;

    public function __construct() {
        $this->itemModel = new ItemEntrega();
        $this->entregaModel = new EntregaEpi();
        $this->funcionarioModel = new Funcionario();
    }

    /**
     * POST /devolucoes
     * Efetua a devolução de um EPI específico contido em um termo de entrega
     */
    public function store(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR']);
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['item_id'])) {
            Response::json(false, "O campo item_id é obrigatório para registrar a devolução.", null, 422);
        }

        $itemId = (int)$input['item_id'];
        $status = $input['item_status'] ?? 'DEVOLVIDO'; // 'DEVOLVIDO', 'EXTRAVIADO'

        if (!in_array($status, ['DEVOLVIDO', 'EXTRAVIADO'], true)) {
            Response::json(false, "Status de devolução inválido. Escolha 'DEVOLVIDO' ou 'EXTRAVIADO'.", null, 422);
        }

        $item = $this->itemModel->findById($itemId);
        if (!$item) {
            Response::json(false, "Item de entrega não localizado.", null, 404);
        }

        if ($item['item_status'] !== 'ENTREGUE') {
            Response::json(false, "Este EPI já está com status '{$item['item_status']}' e não pode ser devolvido.", null, 400);
        }

        $entrega = $this->entregaModel->findById((int)$item['entr_id']);
        if (!$entrega) {
            Response::json(false, "Termo de entrega vinculado não encontrado.", null, 404);
        }

        $motivo = isset($input['item_devolucao_motivo']) ? trim((string)$input['item_devolucao_motivo']) : ($input['motivo'] ?? null);
        $condicao = isset($input['item_devolucao_condicao']) ? trim((string)$input['item_devolucao_condicao']) : ($input['condicao'] ?? null);
        $destino = isset($input['item_devolucao_destino']) ? trim((string)$input['item_devolucao_destino']) : ($input['destino'] ?? null);
        $obs = isset($input['item_devolucao_obs']) ? trim((string)$input['item_devolucao_obs']) : ($input['observacao'] ?? null);

        try {
            $this->itemModel->devolver($itemId, $status, $motivo, $condicao, $destino, $obs);
            
            // Grava Log de Auditoria
            $detalhesLog = json_encode([
                'ocorrencia' => "EPI de ID {$item['epi_id']} devolvido pelo funcionário de ID {$entrega['fun_id']}. Status: {$status}.",
                'motivo' => $motivo,
                'condicao' => $condicao,
                'destino' => $destino,
                'observacao' => $obs
            ], JSON_UNESCAPED_UNICODE);
            Audit::log(
                "DEVOLUÇÃO", 
                "Itens_Entrega", 
                $itemId, 
                $detalhesLog,
                null,
                (int)$entrega['fun_id'],
                (int)$item['epi_id'],
                (int)$item['entr_id'],
                $itemId
            );

            Response::json(true, "Devolução do EPI registrada com sucesso.");
        } catch (Exception $e) {
            Response::json(false, "Erro ao registrar devolução: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /devolucoes/funcionario/{fun_id}
     * Retorna a lista de itens devolvidos/devoluções históricas do funcionário
     */
    public function showByFuncionario(string $funId): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        
        $fId = (int)$funId;
        $funcionario = $this->funcionarioModel->findById($fId, true);
        if (!$funcionario) {
            Response::json(false, "Funcionário não encontrado.", null, 404);
        }

        // Traz as entregas do funcionário para pegar os itens
        $entregas = $this->entregaModel->findByFuncionarioId($fId);
        $devolucoes = [];

        foreach ($entregas as $entrega) {
            $itens = $this->itemModel->findByEntregaId((int)$entrega['entr_id']);
            foreach ($itens as $item) {
                // Seleciona apenas itens que já foram devolvidos ou extraviados
                if ($item['item_status'] === 'DEVOLVIDO' || $item['item_status'] === 'EXTRAVIADO') {
                    $devolucoes[] = [
                        'item_id' => (int)$item['item_id'],
                        'entr_id' => (int)$item['entr_id'],
                        'epi_id' => (int)$item['epi_id'],
                        'epi_nome' => $item['epi_nome'],
                        'epi_ca' => $item['epi_ca'],
                        'item_quantidade' => (int)$item['item_quantidade'],
                        'item_data_devolucao' => $item['item_data_devolucao'],
                        'item_status' => $item['item_status'],
                        'entr_data_entrega' => $entrega['entr_data_entrega']
                    ];
                }
            }
        }

        Response::json(true, "Devoluções históricas localizadas com sucesso.", $devolucoes);
    }
}
