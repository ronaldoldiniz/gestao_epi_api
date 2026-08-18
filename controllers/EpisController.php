<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Core\Audit;
use Models\Epi;
use Exception;

class EpisController {
    private Epi $epiModel;

    public function __construct() {
        $this->epiModel = new Epi();
    }

    /**
     * GET /epis
     */
    public function index(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        
        $epis = $this->epiModel->findAll();
        Response::json(true, "EPIs listados com sucesso.", $epis);
    }

    /**
     * GET /epis/{id}
     */
    public function show(string $id): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        
        $epi = $this->epiModel->findById((int)$id);
        if (!$epi) {
            Response::json(false, "EPI não encontrado.", null, 404);
        }

        Response::json(true, "EPI localizado com sucesso.", $epi);
    }

    /**
     * POST /epis
     */
    public function store(): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST']);
        
        $input = json_decode(file_get_contents('php://input'), true);

        // Validação dos dados obrigatórios
        $required = ['epi_nome', 'epi_ca', 'epi_vencimento_ca', 'epi_validade_uso_dias', 'epi_valor', 'epi_origem_preco'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
                Response::json(false, "O campo '{$field}' é obrigatório.", null, 422);
            }
        }

        try {
            $epiId = $this->epiModel->create($input, (int)$currentUser['usu_id']);
            Audit::log("Cadastrou um novo EPI", "EPIs", $epiId, "EPI: " . $input['epi_nome'] . " (C.A. " . $input['epi_ca'] . ")", null, null, $epiId);
            
            $novo = $this->epiModel->findById($epiId);
            Response::json(true, "EPI cadastrado com sucesso.", $novo, 201);
        } catch (Exception $e) {
            Response::json(false, "Falha ao cadastrar EPI: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * PUT /epis/{id}
     */
    public function update(string $id): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST']);
        
        $epiId = (int)$id;
        $epi = $this->epiModel->findById($epiId);
        if (!$epi) {
            Response::json(false, "EPI não encontrado.", null, 404);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input)) {
            Response::json(false, "Nenhum dado informado para atualização.", null, 400);
        }

        try {
            $this->epiModel->update($epiId, $input, (int)$currentUser['usu_id']);
            Audit::log("Atualizou dados de um EPI", "EPIs", $epiId, "Campos editados: " . implode(", ", array_keys($input)), null, null, $epiId);

            $atualizado = $this->epiModel->findById($epiId);
            Response::json(true, "EPI atualizado com sucesso.", $atualizado);
        } catch (Exception $e) {
            Response::json(false, "Falha ao atualizar EPI: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * DELETE /epis/{id} (Exclusão lógica)
     */
    public function destroy(string $id): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST']);
        
        $epiId = (int)$id;
        $epi = $this->epiModel->findById($epiId);
        if (!$epi) {
            Response::json(false, "EPI não encontrado.", null, 404);
        }

        try {
            $this->epiModel->delete($epiId);
            Audit::log("Inativou EPI (Exclusão lógica)", "EPIs", $epiId, "Nome: " . $epi['epi_nome'], null, null, $epiId);
            Response::json(true, "EPI inativado com sucesso.");
        } catch (Exception $e) {
            Response::json(false, "Falha ao inativar EPI: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /epis/vencidos
     */
    public function showExpired(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        
        $vencidos = $this->epiModel->findExpiredCa();
        Response::json(true, "EPIs com C.A. vencido listados com sucesso.", $vencidos);
    }

    /**
     * GET /epis/proximos-vencimento
     */
    public function showNextExpiration(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        
        // Permite filtrar por dias na query string (default 30)
        $dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 30;
        if ($dias <= 0) $dias = 30;

        $proximos = $this->epiModel->findNextExpirationCa($dias);
        Response::json(true, "EPIs próximos ao vencimento do C.A. (dentro de {$dias} dias) listados com sucesso.", $proximos);
    }
}
