<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Models\EntregaEpi;
use Models\ItemEntrega;

class OperacoesController {
    private EntregaEpi $entregaModel;
    private ItemEntrega $itemModel;

    public function __construct() {
        $this->entregaModel = new EntregaEpi();
        $this->itemModel = new ItemEntrega();
    }

    /**
     * GET /operacoes/{client_operation_id}/status
     * Retorna o status de uma operação pelo client_operation_id (usado para reconciliação)
     */
    public function status(string $clientOperationId): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR']);

        $entrega = $this->entregaModel->findByClientOperationId($clientOperationId);

        if (!$entrega) {
            // Operação não encontrada — pode ser que ainda não foi commitada ou o ID não existe
            Response::json(true, "Operação não localizada.", [
                'status' => 'NAO_ENCONTRADA'
            ]);
            return;
        }

        $status = $entrega['entr_status'] === 'FINALIZADA' ? 'CONCLUIDA' : 'FALHOU';

        $responseData = [
            'status' => $status,
            'data' => [
                'entr_id' => (int)$entrega['entr_id'],
                'entr_hash_assinatura' => $entrega['entr_hash_assinatura']
            ]
        ];

        Response::json(true, "Status da operação recuperado com sucesso.", $responseData);
    }
}
