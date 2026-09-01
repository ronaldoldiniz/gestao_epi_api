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
use PDO;

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
     * Efetua a devolucao de um EPI especifico contido em um termo de entrega (com idempotencia)
     */
    public function store(): void {
        $currentUser = Auth::requireAuth(["ADMINISTRADOR", "TECNICO_SST", "ALMOXARIFE_OPERADOR"]);
        $input = json_decode(file_get_contents("php://input"), true);

        if (!isset($input["item_id"])) {
            Response::json(false, "O campo item_id e obrigatorio para registrar a devolucao.", null, 422);
        }

        $itemId = (int)$input["item_id"];
        $status = $input["item_status"] ?? "DEVOLVIDO"; // "DEVOLVIDO", "EXTRAVIADO"
        $clientOperationId = isset($input["client_operation_id"]) ? trim((string)$input["client_operation_id"]) : null;
        $deviceId = isset($input["device_id"]) ? trim((string)$input["device_id"]) : null;
        $operationOrigin = isset($input["operation_origin"]) ? strtoupper(trim((string)$input["operation_origin"])) : "ONLINE";

        if (!in_array($status, ["DEVOLVIDO", "EXTRAVIADO"], true)) {
            Response::json(false, "Status de devolucao invalido. Escolha DEVOLVIDO ou EXTRAVIADO.", null, 422);
        }

        $db = Database::getConnection();

        // 1. Verificacao de Idempotencia
        if ($clientOperationId) {
            $stmtOperacao = $db->prepare("SELECT * FROM operacoes_idempotentes WHERE ope_client_operation_id = :op_id");
            $stmtOperacao->execute([":op_id" => $clientOperationId]);
            $operacao = $stmtOperacao->fetch(PDO::FETCH_ASSOC);

            if ($operacao) {
                if ($operacao["ope_status"] === "CONCLUIDA") {
                    $origResponse = json_decode($operacao["ope_resposta_json"], true);
                    Response::json(true, "A devolucao ja havia sido concluida.", $origResponse, 200);
                } elseif ($operacao["ope_status"] === "PROCESSANDO") {
                    Response::json(false, "Operacao em processamento concorrente.", [
                        "_is_custom_payload" => true,
                        "code" => "OPERACAO_EM_PROCESSAMENTO"
                    ], 409);
                }
            }

            try {
                $stmtInsertOp = $db->prepare("INSERT INTO operacoes_idempotentes (ope_client_operation_id, ope_tipo_operacao, usuario_id, ope_status, ope_data_hora_inicio) VALUES (:op_id, \"DEVOLUCAO\", :usu_id, \"PROCESSANDO\", NOW())");
                $stmtInsertOp->execute([
                    ":op_id" => $clientOperationId,
                    ":usu_id" => (int)$currentUser["usu_id"]
                ]);
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), "1062") !== false || strpos($e->getMessage(), "Duplicate entry") !== false) {
                    $stmtOperacao = $db->prepare("SELECT * FROM operacoes_idempotentes WHERE ope_client_operation_id = :op_id");
                    $stmtOperacao->execute([":op_id" => $clientOperationId]);
                    $operacao = $stmtOperacao->fetch(PDO::FETCH_ASSOC);
                    if ($operacao) {
                        if ($operacao["ope_status"] === "CONCLUIDA") {
                            $origResponse = json_decode($operacao["ope_resposta_json"], true);
                            Response::json(true, "A devolucao ja havia sido concluida.", $origResponse, 200);
                        } elseif ($operacao["ope_status"] === "PROCESSANDO") {
                            Response::json(false, "Operacao em processamento concorrente.", [
                                "_is_custom_payload" => true,
                                "code" => "OPERACAO_EM_PROCESSAMENTO"
                            ], 409);
                        }
                    }
                }
                throw $e;
            }
        }

        $item = $this->itemModel->findById($itemId);
        if (!$item) {
            if ($clientOperationId) {
                $db->prepare("UPDATE operacoes_idempotentes SET ope_status = \"FALHOU\", ope_codigo_resultado = \"NAO_ENCONTRADO\" WHERE ope_client_operation_id = :op_id")->execute([":op_id" => $clientOperationId]);
            }
            Response::json(false, "Item de entrega nao localizado.", null, 404);
        }

        if ($item["item_status"] !== "ENTREGUE") {
            if ($clientOperationId) {
                $db->prepare("UPDATE operacoes_idempotentes SET ope_status = \"CONCLUIDA\", ope_codigo_resultado = \"JA_DEVOLVIDO\" WHERE ope_client_operation_id = :op_id")->execute([":op_id" => $clientOperationId]);
            }
            Response::json(true, "Este EPI ja se encontra com status \"" . $item["item_status"] . "\". Operacao tratada como concluida.", [
                "item_id" => $itemId,
                "status" => $item["item_status"],
                "already_processed" => true
            ], 200);
        }

        $entrega = $this->entregaModel->findById((int)$item["entr_id"]);
        if (!$entrega) {
            if ($clientOperationId) {
                $db->prepare("UPDATE operacoes_idempotentes SET ope_status = \"FALHOU\", ope_codigo_resultado = \"TERMO_NAO_ENCONTRADO\" WHERE ope_client_operation_id = :op_id")->execute([":op_id" => $clientOperationId]);
            }
            Response::json(false, "Termo de entrega vinculado nao encontrado.", null, 404);
        }

        $motivo = isset($input["item_devolucao_motivo"]) ? trim((string)$input["item_devolucao_motivo"]) : ($input["motivo"] ?? null);
        $condicao = isset($input["item_devolucao_condicao"]) ? trim((string)$input["item_devolucao_condicao"]) : ($input["condicao"] ?? null);
        $destino = isset($input["item_devolucao_destino"]) ? trim((string)$input["item_devolucao_destino"]) : ($input["destino"] ?? null);
        $obs = isset($input["item_devolucao_obs"]) ? trim((string)$input["item_devolucao_obs"]) : ($input["observacao"] ?? null);

        try {
            $db->beginTransaction();

            $this->itemModel->devolver($itemId, $status, $motivo, $condicao, $destino, $obs);
            
            // Grava Log de Auditoria
            $detalhesLog = json_encode([
                "ocorrencia" => "EPI de ID " . $item["epi_id"] . " devolvido pelo funcionario de ID " . $entrega["fun_id"] . ". Status: " . $status . ". Origem: " . $operationOrigin . ".",
                "origem" => $operationOrigin,
                "device_id" => $deviceId,
                "motivo" => $motivo,
                "condicao" => $condicao,
                "destino" => $destino,
                "observacao" => $obs
            ], JSON_UNESCAPED_UNICODE);
            Audit::log(
                "DEVOLUCAO", 
                "Itens_Entrega", 
                $itemId, 
                $detalhesLog,
                null,
                (int)$entrega["fun_id"],
                (int)$item["epi_id"],
                (int)$item["entr_id"],
                $itemId
            );

            $dataResponse = [
                "item_id" => $itemId,
                "status" => $status,
                "client_operation_id" => $clientOperationId,
                "data_devolucao" => date("Y-m-d H:i:s"),
                "already_processed" => false
            ];

            if ($clientOperationId) {
                $stmtUpdate = $db->prepare("UPDATE operacoes_idempotentes SET ope_status = \"CONCLUIDA\", ope_devolucao_id = :dev_id, ope_data_hora_conclusao = NOW(), ope_resposta_json = :json WHERE ope_client_operation_id = :op_id");
                $stmtUpdate->execute([
                    ":dev_id" => $itemId,
                    ":json" => json_encode(array_merge($dataResponse, ["already_processed" => true]), JSON_UNESCAPED_UNICODE),
                    ":op_id" => $clientOperationId
                ]);
            }

            $db->commit();

            Response::json(true, "Devolucao do EPI registrada com sucesso.", $dataResponse);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($clientOperationId) {
                $db->prepare("UPDATE operacoes_idempotentes SET ope_status = \"FALHOU\", ope_codigo_resultado = \"ERRO_EXCEPTION\" WHERE ope_client_operation_id = :op_id")->execute([":op_id" => $clientOperationId]);
            }
            Response::json(false, "Erro ao registrar devolucao: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /devolucoes/funcionario/{fun_id}
     * Retorna a lista de itens devolvidos/devolucoes historicas do funcionario
     */
    public function showByFuncionario(string $funId): void {
        Auth::requireAuth(["ADMINISTRADOR", "TECNICO_SST", "ALMOXARIFE_OPERADOR", "GESTOR"]);
        
        $fId = (int)$funId;
        $funcionario = $this->funcionarioModel->findById($fId, true);
        if (!$funcionario) {
            Response::json(false, "Funcionario nao encontrado.", null, 404);
        }

        // Traz as entregas do funcionario para pegar os itens
        $entregas = $this->entregaModel->findByFuncionarioId($fId);
        $devolucoes = [];

        foreach ($entregas as $entrega) {
            $itens = $this->itemModel->findByEntregaId((int)$entrega["entr_id"]);
            foreach ($itens as $item) {
                // Seleciona apenas itens que ja foram devolvidos ou extraviados
                if ($item["item_status"] === "DEVOLVIDO" || $item["item_status"] === "EXTRAVIADO") {
                    $devolucoes[] = [
                        "item_id" => (int)$item["item_id"],
                        "entr_id" => (int)$item["entr_id"],
                        "epi_id" => (int)$item["epi_id"],
                        "epi_nome" => $item["epi_nome"],
                        "epi_ca" => $item["epi_ca"],
                        "item_quantidade" => (int)$item["item_quantidade"],
                        "item_data_devolucao" => $item["item_data_devolucao"],
                        "item_status" => $item["item_status"],
                        "entr_data_entrega" => $entrega["entr_data_entrega"]
                    ];
                }
            }
        }

        Response::json(true, "Devolucoes historicas localizadas com sucesso.", $devolucoes);
    }
}
