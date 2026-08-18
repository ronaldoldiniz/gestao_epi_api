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

        $tipoItem = $input['epi_tipo_item'] ?? 'EPI_COM_CA';
        $tiposPermitidos = ['EPI_COM_CA', 'ITEM_SEGURANCA_SEM_CA', 'UNIFORME', 'OUTRO'];
        if (!in_array($tipoItem, $tiposPermitidos, true)) {
            Response::json(false, "Tipo de item inválido.", null, 422);
            return;
        }

        // Campos obrigatórios independentes do tipo
        $required = ['epi_nome', 'epi_fabricante', 'epi_valor', 'epi_origem_preco'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
                Response::json(false, "O campo '{$field}' é obrigatório.", null, 422);
                return;
            }
        }

        // Campos obrigatórios apenas para EPI com C.A.
        if ($tipoItem === 'EPI_COM_CA') {
            $requiredCa = ['epi_ca', 'epi_vencimento_ca'];
            foreach ($requiredCa as $field) {
                if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
                    Response::json(false, "O campo '{$field}' é obrigatório para EPIs com Certificado de Aprovação.", null, 422);
                    return;
                }
            }
        }

        // Para item sem C.A., Uniforme ou Outro exigir ao menos um dado de rastreabilidade (exclui lote no cadastro fixo conforme TCC)
        if (in_array($tipoItem, ['ITEM_SEGURANCA_SEM_CA', 'UNIFORME', 'OUTRO'], true)) {
            $temRastreabilidade =
                !empty(trim((string)($input['epi_modelo'] ?? ''))) ||
                !empty(trim((string)($input['epi_identificacao'] ?? ''))) ||
                !empty(trim((string)($input['epi_ref_fornecedor'] ?? '')));

            if (!$temRastreabilidade) {
                Response::json(false,
                    "Para itens de segurança sem C.A., uniforme ou outros, informe ao menos um dado de rastreabilidade: modelo, identificação interna ou referência do fornecedor.",
                    null, 422);
                return;
            }
        }

        // Validação condicional para vida útil se for CONTROLADO
        if (isset($input['epi_vida_util_tipo']) && $input['epi_vida_util_tipo'] === 'CONTROLADO') {
            if (!isset($input['epi_vida_util']) || trim((string)$input['epi_vida_util']) === '' || (int)$input['epi_vida_util'] <= 0) {
                Response::json(false, "O campo 'Vida Útil' é obrigatório e deve ser maior que zero quando o controle for 'CONTROLADO'.", null, 422);
                return;
            }
            if (!isset($input['epi_vida_util_unidade']) || trim((string)$input['epi_vida_util_unidade']) === '') {
                Response::json(false, "A unidade da vida útil é obrigatória quando o controle for 'CONTROLADO'.", null, 422);
                return;
            }
        }

        try {
            $epiId = $this->epiModel->create($input, (int)$currentUser['usu_id']);
            \Core\Audit::logCadastro("EPIs", $epiId, $input['epi_nome'], $input);
            
            $novo = $this->epiModel->findById($epiId);
            Response::json(true, "Item cadastrado com sucesso.", $novo, 201);
        } catch (Exception $e) {
            Response::json(false, "Falha ao cadastrar item: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * PUT /epis/{id}
     */
    public function update(string $id): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'TECNICO_SST']);
        
        $epiId = (int)$id;
        $epi = $this->epiModel->findById($epiId);
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$epi) {
            // Se o EPI não existe no MySQL, cria com o ID especificado
            try {
                $this->epiModel->createWithId($epiId, $input, (int)$currentUser['usu_id']);
                \Core\Audit::logCadastro("EPIs", $epiId, $input['epi_nome'], $input);
                $atualizado = $this->epiModel->findById($epiId);
                Response::json(true, "EPI atualizado com sucesso (sincronizado).", $atualizado);
                return;
            } catch (Exception $e) {
                Response::json(false, "Falha ao criar EPI inexistente no update: " . $e->getMessage(), null, 500);
                return;
            }
        }

        if (empty($input)) {
            Response::json(false, "Nenhum dado informado para atualização.", null, 400);
        }

        // Validação condicional para atualização de vida útil se for CONTROLADO
        $tipoControle = $input['epi_vida_util_tipo'] ?? $epi['epi_vida_util_tipo'] ?? 'CONTROLADO';
        if ($tipoControle === 'CONTROLADO') {
            if (isset($input['epi_vida_util']) && ((int)$input['epi_vida_util'] <= 0 || trim((string)$input['epi_vida_util']) === '')) {
                Response::json(false, "O campo 'Vida Útil' é obrigatório e deve ser maior que zero quando o controle for 'CONTROLADO'.", null, 422);
                return;
            }
            if (isset($input['epi_vida_util_unidade']) && trim((string)$input['epi_vida_util_unidade']) === '') {
                Response::json(false, "A unidade da vida útil é obrigatória quando o controle for 'CONTROLADO'.", null, 422);
                return;
            }
        }

        try {
            $this->epiModel->update($epiId, $input, (int)$currentUser['usu_id']);
            \Core\Audit::compareAndLog("ALTERAÇÃO", "EPIs", $epiId, $epi, $input);

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
            \Core\Audit::logInativacao("EPIs", $epiId, $epi['epi_nome']);
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
