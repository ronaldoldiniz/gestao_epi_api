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
        Auth::requireAuth(["ADMINISTRADOR", "TECNICO_SST", "ALMOXARIFE_OPERADOR", "GESTOR", "RH_ADMINISTRATIVO"]);
        
        $entregas = $this->entregaModel->findAll();
        foreach ($entregas as &$entrega) {
            $entrega["itens"] = $this->itemModel->findByEntregaId((int)$entrega["entr_id"]);
        }
        Response::json(true, "Entregas listadas com sucesso.", $entregas);
    }

    /**
     * GET /entregas/{id}
     */
    public function show(string $id): void {
        Auth::requireAuth(["ADMINISTRADOR", "TECNICO_SST", "ALMOXARIFE_OPERADOR", "GESTOR", "RH_ADMINISTRATIVO"]);
        
        $entrId = (int)$id;
        $entrega = $this->entregaModel->findById($entrId);
        if (!$entrega) {
            Response::json(false, "Entrega de EPI nao encontrada.", null, 404);
        }

        $itens = $this->itemModel->findByEntregaId($entrId);
        $entrega["itens"] = $itens;

        Response::json(true, "Entrega localizada com sucesso.", $entrega);
    }

    /**
     * GET /entregas/funcionario/{fun_id}
     */
    public function showByFuncionario(string $funId): void {
        Auth::requireAuth(["ADMINISTRADOR", "TECNICO_SST", "ALMOXARIFE_OPERADOR", "GESTOR", "RH_ADMINISTRATIVO"]);
        
        $fId = (int)$funId;
        $funcionario = $this->funcionarioModel->findById($fId, true);
        if (!$funcionario) {
            Response::json(false, "Funcionario nao encontrado.", null, 404);
        }

        $entregas = $this->entregaModel->findByFuncionarioId($fId);
        foreach ($entregas as &$entrega) {
            $entrega["itens"] = $this->itemModel->findByEntregaId((int)$entrega["entr_id"]);
        }

        Response::json(true, "Entregas do funcionario recuperadas com sucesso.", $entregas);
    }

    /**
     * POST /entregas (Registro de entrega em Transacao com Idempotencia)
     */
    public function store(): void {
        $currentUser = Auth::requireAuth(["ADMINISTRADOR", "TECNICO_SST", "ALMOXARIFE_OPERADOR"]);
        $input = json_decode(file_get_contents("php://input"), true);

        // 1. Receber dados
        if (!isset($input["fun_id"]) || !isset($input["entr_motivo"]) || !isset($input["itens"]) || !is_array($input["itens"])) {
            Response::json(false, "Os campos fun_id, entr_motivo e itens (como array) sao obrigatorios.", null, 422);
        }

        $funId = (int)$input["fun_id"];
        $motivo = trim($input["entr_motivo"]);
        $itens = $input["itens"];
        $clientOperationId = isset($input["client_operation_id"]) ? trim((string)$input["client_operation_id"]) : null;
        $operationOrigin = isset($input["operation_origin"]) ? strtoupper(trim((string)$input["operation_origin"])) : "ONLINE";
        $isOffline = ($operationOrigin === "OFFLINE");
        $deviceId = isset($input["device_id"]) ? trim((string)$input["device_id"]) : null;
        $hashAssinaturaRecebido = isset($input["hash_assinatura"]) ? trim((string)$input["hash_assinatura"]) : null;
        $dataHoraAssinatura = isset($input["data_hora_assinatura"]) ? trim((string)$input["data_hora_assinatura"]) : date("Y-m-d H:i:s");

        // Tratamento do PIN de acordo com a origem da operacao
        if (!$isOffline) {
            if (!isset($input["pin"]) || trim((string)$input["pin"]) === "") {
                Response::json(false, "O PIN e obrigatorio para registrar a entrega online.", null, 422);
            }
            $pin = trim((string)$input["pin"]);
            if (strlen($pin) < 4 || strlen($pin) > 10) {
                Response::json(false, "O PIN deve possuir entre 4 e 10 caracteres.", null, 422);
            }
        } else {
            $pin = null; // Em operacoes offline o PIN nao e trafegado em texto puro
        }

        if (empty($itens)) {
            Response::json(false, "E necessario informar ao menos um EPI para a entrega.", null, 422);
        }

        // Obtem conexao com banco de dados
        $db = Database::getConnection();

        // 2. Verificacao de idempotencia antes de qualquer alteracao ou autenticacao
        if ($clientOperationId) {
            $stmtOperacao = $db->prepare("SELECT * FROM operacoes_idempotentes WHERE ope_client_operation_id = :op_id");
            $stmtOperacao->execute([":op_id" => $clientOperationId]);
            $operacao = $stmtOperacao->fetch(PDO::FETCH_ASSOC);

            if ($operacao) {
                if ($operacao["ope_status"] === "CONCLUIDA") {
                    $origResponse = json_decode($operacao["ope_resposta_json"], true);
                    Response::json(true, "A operacao ja havia sido concluida.", $origResponse, 200);
                } elseif ($operacao["ope_status"] === "PROCESSANDO") {
                    Response::json(false, "Operacao em processamento concorrente.", [
                        "_is_custom_payload" => true,
                        "code" => "OPERACAO_EM_PROCESSAMENTO"
                    ], 409);
                }
            }

            // Registra inicialmente a operacao com status PROCESSANDO (trava concorrencia por constraint UNIQUE)
            try {
                $stmtInsertOp = $db->prepare("INSERT INTO operacoes_idempotentes (ope_client_operation_id, ope_tipo_operacao, usuario_id, fun_id, ope_status, ope_data_hora_inicio) VALUES (:op_id, \"ENTREGA_COM_DEVOLUCAO\", :usu_id, :fun_id, \"PROCESSANDO\", NOW())");
                $stmtInsertOp->execute([
                    ":op_id" => $clientOperationId,
                    ":usu_id" => (int)$currentUser["usu_id"],
                    ":fun_id" => $funId
                ]);
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), "1062") !== false || strpos($e->getMessage(), "Duplicate entry") !== false) {
                    $stmtOperacao = $db->prepare("SELECT * FROM operacoes_idempotentes WHERE ope_client_operation_id = :op_id");
                    $stmtOperacao->execute([":op_id" => $clientOperationId]);
                    $operacao = $stmtOperacao->fetch(PDO::FETCH_ASSOC);
                    if ($operacao) {
                        if ($operacao["ope_status"] === "CONCLUIDA") {
                            $origResponse = json_decode($operacao["ope_resposta_json"], true);
                            Response::json(true, "A operacao ja havia sido concluida.", $origResponse, 200);
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

        // 3. Validar se funcionario existe e esta apto
        $funcionario = $this->funcionarioModel->findById($funId, false);
        if (!$funcionario) {
            Response::json(false, "Funcionario nao encontrado.", null, 404);
        }

        if (in_array($funcionario["fun_situacao"], ["PENDENTE_SENHA", "INATIVO", "AFASTADO", "DEMITIDO"]) || $funcionario["fun_situacao"] !== "ATIVO") {
            Response::json(false, "Entrega de EPI nao permitida. O funcionario esta com o status \"" . $funcionario["fun_situacao"] . "\".", null, 403);
        }

        // 4. Validar se assinatura eletronica esta cadastrada e ativa
        $assinatura = $this->assinaturaModel->findByFuncionarioId($funId);
        if (!$assinatura) {
            Response::json(false, "Assinatura eletronica nao cadastrada para este funcionario. Solicite o cadastro no RH.", null, 403);
        }

        if ($assinatura["ass_status"] === "BLOQUEADO") {
            Response::json(false, "A assinatura eletronica deste funcionario esta BLOQUEADA. Solicite o desbloqueio no RH.", null, 403);
        }

        if ($assinatura["ass_status"] !== "ATIVO") {
            Response::json(false, "A assinatura eletronica deste funcionario nao esta ativa no momento.", null, 403);
        }

        // 5. Validar PIN/senha (se for operacao online)
        $configFile = dirname(__DIR__) . "/config/config.php";
        if (!file_exists($configFile)) {
            $configFile = dirname(__DIR__) . "/config/config.example.php";
        }
        $config = require $configFile;
        $maxAttempts = $config["security"]["max_pin_attempts"] ?? 3;
        $secretKey = $config["app"]["secret_key"];

        if (!$isOffline) {
            if (!\Security\PasswordService::verify($pin, $assinatura["ass_salt"] ?? "", $assinatura["ass_senha_hash"])) {
                $attempts = $this->assinaturaModel->incrementFailAttempts((int)$assinatura["ass_id"], $maxAttempts);
                
                $detalhesLog = json_encode(["ocorrencia" => "Falha de PIN na entrega online. Tentativa {$attempts} de {$maxAttempts} para o funcionario: " . $funcionario["fun_nome"]], JSON_UNESCAPED_UNICODE);
                Audit::log("VALIDACAO", "Entrega_EPIs", null, $detalhesLog, null, $funId, null, null, null, (int)$assinatura["ass_id"]);

                if ($attempts >= $maxAttempts) {
                    Response::json(false, "PIN incorreto. A assinatura eletronica foi BLOQUEADA por excesso de erros.", null, 401);
                } else {
                    $restantes = $maxAttempts - $attempts;
                    Response::json(false, "PIN incorreto. Restam {$restantes} tentativa(s) antes do bloqueio.", null, 401);
                }
            }
        }

        try {
            // 6. Iniciar transacao MySQL
            $db->beginTransaction();

            // 6.1. Regra de negocio: verifica duplicidade de EPI em uso sem devolucao vinculada
            $contagemEntregue = [];
            $contagemDevolucao = [];
            foreach ($itens as $item) {
                if (!isset($item["epi_id"])) {
                    continue;
                }
                $epiIdItem = (int)$item["epi_id"];
                $contagemEntregue[$epiIdItem] = ($contagemEntregue[$epiIdItem] ?? 0) + 1;
                if (isset($item["devolucao_vinculada"]["item_id_anterior"])) {
                    $contagemDevolucao[$epiIdItem] = ($contagemDevolucao[$epiIdItem] ?? 0) + 1;
                }
            }
            foreach ($contagemEntregue as $epiIdItem => $qtdEntregue) {
                $qtdDevolucao = $contagemDevolucao[$epiIdItem] ?? 0;
                $stmtEmUso = $db->prepare(
                    "SELECT COUNT(*) FROM itens_entrega i
                     JOIN entrega_epis e ON i.entr_id = e.entr_id
                     WHERE e.fun_id = :fun_id AND i.epi_id = :epi_id
                       AND i.item_status = \"ENTREGUE\" AND i.item_data_devolucao IS NULL"
                );
                $stmtEmUso->execute([":fun_id" => $funId, ":epi_id" => $epiIdItem]);
                $qtdEmUso = (int)$stmtEmUso->fetchColumn();
                if (($qtdEmUso - $qtdDevolucao > 0) && ($qtdEntregue - $qtdDevolucao > 0)) {
                    throw new Exception("O EPI ID {$epiIdItem} ja esta em uso por este funcionario e ha entrega deste item sem devolucao vinculada agendada.");
                }
            }

            // Recuperar Termo Vigente do Banco de Dados
            $stmtTermo = $db->query("SELECT termo_id, termo_versao, termo_texto_completo FROM termos_responsabilidade WHERE termo_status = \"ATIVO\" LIMIT 1");
            $termoAtivo = $stmtTermo->fetch(PDO::FETCH_ASSOC);

            $termoId = $termoAtivo ? (int)$termoAtivo["termo_id"] : null;
            $termoVersao = $termoAtivo ? $termoAtivo["termo_versao"] : "1.0";
            $textoTermo = $termoAtivo ? $termoAtivo["termo_texto_completo"] : "Declaro ter recebido os EPIs listados nesta entrega...";

            $ipOrigem = $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";
            $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "GestaoEpi_AndroidApp";

            // Gera ou preserva o hash da assinatura
            if ($hashAssinaturaRecebido && strlen($hashAssinaturaRecebido) >= 16) {
                $hashAssinatura = $hashAssinaturaRecebido;
            } else {
                $dadosParaHash = $funId . "|" . $assinatura["ass_id"] . "|" . time() . "|" . json_encode($itens) . "|" . $termoVersao . "|" . $ipOrigem;
                $hashAssinatura = hash_hmac("sha256", $dadosParaHash, $secretKey);
            }

            $validacaoSenha = $isOffline ? "VALIDADA_LOCALMENTE_OFFLINE" : "VALIDADA";
            $metodoAceite = $isOffline ? "PIN_ELETRONICO_OFFLINE" : "PIN_ELETRONICO";

            // 7. Criar cabecalho em Entrega_EPIs
            $entrId = $this->entregaModel->create([
                "fun_id" => $funId,
                "usu_id" => (int)$currentUser["usu_id"],
                "ass_id" => (int)$assinatura["ass_id"],
                "entr_hash_assinatura" => $hashAssinatura,
                "entr_termo_ciencia" => "SIM",
                "entr_status" => "FINALIZADA",
                "entr_status_sinc" => "SINCRONIZADO",
                "entr_validacao_senha" => $validacaoSenha,
                "entr_motivo" => $motivo,
                "client_operation_id" => $clientOperationId,
                "termo_id" => $termoId,
                "entr_termo_versao" => $termoVersao,
                "entr_texto_termo_snapshot" => $textoTermo,
                "entr_data_hora_aceite" => $dataHoraAssinatura,
                "entr_metodo_aceite" => $metodoAceite,
                "entr_hash_termo" => hash("sha256", $textoTermo)
            ]);

            // 8. Criar itens em Itens_Entrega e validar existencias de EPIs
            $hasDevolucaoVinculada = false;
            $devolucoesProcessed = [];
            $novoItemId = null;
            foreach ($itens as $item) {
                if (!isset($item["epi_id"]) || !isset($item["item_quantidade"]) || (int)$item["item_quantidade"] <= 0) {
                    throw new Exception("Dados de item de entrega invalidos ou quantidade zerada.");
                }

                $epiId = (int)$item["epi_id"];
                $qtd = (int)$item["item_quantidade"];

                $epi = $this->epiModel->findById($epiId);
                if (!$epi || $epi["epi_status"] !== "ATIVO") {
                    throw new Exception("EPI ID {$epiId} nao encontrado ou inativo no sistema.");
                }

                $novoItemId = $this->itemModel->create([
                    "entr_id" => $entrId,
                    "epi_id" => $epiId,
                    "item_quantidade" => $qtd,
                    "item_status" => "ENTREGUE",
                    "item_numero_lote" => $item["item_numero_lote"] ?? null,
                    "item_tamanho" => $item["item_tamanho"] ?? null,
                    "item_motivo_entrega" => $item["item_motivo_entrega"] ?? $motivo,
                    "item_epi_nome_snapshot" => $epi["epi_nome"],
                    "item_epi_descricao_snapshot" => $epi["epi_descricao"] ?? "",
                    "item_epi_fabricante_snapshot" => $epi["epi_fabricante"],
                    "item_epi_modelo_snapshot" => $epi["epi_modelo"] ?? "",
                    "item_epi_ca_snapshot" => $epi["epi_ca"] ?? "NAO REGISTRADO",
                    "item_epi_validade_ca_snapshot" => $epi["epi_vencimento_ca"] ?? null,
                    "item_epi_vida_util_snapshot" => $epi["epi_validade_uso_dias"] ?? 0,
                    "item_epi_valor_snapshot" => $epi["epi_valor"] ?? 0.00,
                    "item_epi_origem_preco_snapshot" => $epi["epi_origem_preco"] ?? "COMPRA_DIRETA",
                    "item_epi_localizacao_snapshot" => $epi["epi_localizacao"] ?? ""
                ]);

                // Processamento de Devolucao Vinculada Simultanea
                if (isset($item["devolucao_vinculada"]) && is_array($item["devolucao_vinculada"])) {
                    $dev = $item["devolucao_vinculada"];
                    $itemAnteriorId = (int)$dev["item_id_anterior"];
                    $qtdDevolver = (int)$dev["quantidade_devolvida"];
                    $motivoDev = $dev["motivo"] ?? "SUBSTITUICAO_PROGRAMADA";
                    $condicaoDev = $dev["condicao"] ?? "DESGASTADO";
                    $destinoDev = $dev["destino"] ?? "DESCARTE";
                    $obsDev = $dev["observacao"] ?? null;

                    $stmtLock = $db->prepare("SELECT * FROM itens_entrega WHERE item_id = :id FOR UPDATE");
                    $stmtLock->execute([":id" => $itemAnteriorId]);
                    $itemAnterior = $stmtLock->fetch(PDO::FETCH_ASSOC);

                    if (!$itemAnterior) {
                        throw new Exception("Item anterior para devolucao vinculada nao encontrado (ID: {$itemAnteriorId}).");
                    }

                    $stmtCheckFunc = $db->prepare("SELECT fun_id FROM entrega_epis WHERE entr_id = :entr_id");
                    $stmtCheckFunc->execute([":entr_id" => $itemAnterior["entr_id"]]);
                    $funItemAnterior = $stmtCheckFunc->fetchColumn();
                    if ((int)$funItemAnterior !== $funId) {
                        throw new Exception("Tentativa de devolucao de EPI pertencente a outro funcionario.");
                    }

                    if ($itemAnterior["item_status"] !== "ENTREGUE") {
                        throw new Exception("O item anterior ja foi devolvido ou nao possui status ativo.");
                    }

                    if ($qtdDevolver <= 0 || $qtdDevolver > (int)$itemAnterior["item_quantidade"]) {
                        throw new Exception("Quantidade para devolucao vinculada invalida ou superior ao saldo em uso.");
                    }

                    // Registra a devolucao no item anterior
                    $this->itemModel->devolver($itemAnteriorId, "DEVOLVIDO", $motivoDev, $condicaoDev, $destinoDev, $obsDev);

                    // Vincula atomicamente o item anterior ao novo termo de entrega
                    $stmtVinculo = $db->prepare("UPDATE itens_entrega SET item_devolucao_vinculo_entrega_id = :entr_id, item_devolucao_vinculo_item_id = :item_id, item_devolucao_tipo_operacao = \"DEVOLUCAO_VINCULADA_A_NOVA_ENTREGA\" WHERE item_id = :item_ant_id");
                    $stmtVinculo->execute([
                        ":entr_id" => $entrId,
                        ":item_id" => $novoItemId,
                        ":item_ant_id" => $itemAnteriorId
                    ]);

                    $hasDevolucaoVinculada = true;
                    $devolucoesProcessed[] = [
                        "devolucao_id" => $itemAnteriorId,
                        "entrega_original_id" => (int)$itemAnterior["entr_id"],
                        "item_original_id" => $itemAnteriorId,
                        "status_item_anterior" => "DEVOLVIDO",
                        "quantidade_em_uso" => 0,
                        "substituicao" => [
                            "item_devolvido_id" => $itemAnteriorId,
                            "novo_item_entregue_id" => $novoItemId,
                            "novo_item_entr_id" => $entrId,
                            "motivo_devolucao" => $motivoDev,
                            "condicao_devolucao" => $condicaoDev,
                            "destino_devolucao" => $destinoDev,
                            "tipo_operacao" => "DEVOLUCAO_VINCULADA_A_NOVA_ENTREGA"
                        ]
                    ];
                }
            }

            // 9. Grava Log de Auditoria
            $detalhesLog = json_encode([
                "versao_log" => 2,
                "tipo_evento" => "ENTREGA_FINALIZADA",
                "resultado" => "SUCESSO",
                "origem" => $operationOrigin,
                "device_id" => $deviceId,
                "entrega" => [
                    "id" => $entrId,
                    "status" => "FINALIZADA",
                    "motivo_geral" => $motivo,
                    "data_finalizacao" => date("Y-m-d H:i:s"),
                    "quantidade_itens" => count($itens),
                    "assinatura_validada" => true,
                    "metodo_aceite" => $metodoAceite
                ],
                "itens" => $itens,
                "ocorrencia" => "Entrega n. {$entrId} finalizada para {$funcionario["fun_nome"]}. Origem: {$operationOrigin}."
            ], JSON_UNESCAPED_UNICODE);

            Audit::log("ENTREGA", "entrega_epis", $entrId, $detalhesLog, null, $funId, null, $entrId, null, (int)$assinatura["ass_id"]);

            // 10. Confirmar transacao no MySQL
            $db->commit();

            $dataResponse = [
                "client_operation_id" => $clientOperationId,
                "entrega_id" => $entrId,
                "entr_hash_assinatura" => $hashAssinatura,
                "data_operacao" => date("Y-m-d H:i:s")
            ];

            if (!empty($devolucoesProcessed)) {
                $firstDev = $devolucoesProcessed[0];
                $dataResponse["item_entrega_id"] = $novoItemId;
                $dataResponse["devolucao_id"] = $firstDev["devolucao_id"];
                $dataResponse["entrega_original_id"] = $firstDev["entrega_original_id"];
                $dataResponse["item_original_id"] = $firstDev["item_original_id"];
                $dataResponse["status_item_anterior"] = $firstDev["status_item_anterior"];
                $dataResponse["quantidade_em_uso"] = $firstDev["quantidade_em_uso"];
            }

            if ($clientOperationId) {
                $storedData = array_merge($dataResponse, ["already_processed" => false]);
                $stmtUpdateOp = $db->prepare("UPDATE operacoes_idempotentes SET ope_status = \"CONCLUIDA\", ope_entrega_id = :entr_id, ope_devolucao_id = :dev_id, ope_data_hora_conclusao = NOW(), ope_resposta_json = :json WHERE ope_client_operation_id = :op_id");
                $stmtUpdateOp->execute([
                    ":entr_id" => $entrId,
                    ":dev_id" => !empty($devolucoesProcessed) ? $devolucoesProcessed[0]["devolucao_id"] : null,
                    ":json" => json_encode(array_merge($storedData, ["already_processed" => true]), JSON_UNESCAPED_UNICODE),
                    ":op_id" => $clientOperationId
                ]);
            }

            $message = $hasDevolucaoVinculada ? "Entrega e devolucao registradas com sucesso." : "Entrega registrada com sucesso.";
            Response::json(true, $message, array_merge($dataResponse, ["already_processed" => false]));
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

            $reference = "ENT-SUB-" . date("Ymd") . "-" . sprintf("%03d", rand(1, 999));
            error_log("[$reference] Erro ao registrar entrega de EPI: " . $e->getMessage());

            $statusCode = 500;
            $code = "ERRO_TRANSACAO_ENTREGA_DEVOLUCAO";
            $message = "Erro ao processar: " . $e->getMessage();
            $extraData = [];

            if (strpos($e->getMessage(), "nao encontrado") !== false || strpos($e->getMessage(), "invalido") !== false || strpos($e->getMessage(), "obrigatorio") !== false) {
                $statusCode = 422;
                $code = "ERRO_VALIDACAO";
                $message = $e->getMessage();
            }

            if ($clientOperationId) {
                $stmtUpdateOpFailed = $db->prepare("UPDATE operacoes_idempotentes SET ope_status = \"FALHOU\", ope_erro_referencia = :ref, ope_codigo_resultado = :code WHERE ope_client_operation_id = :op_id");
                $stmtUpdateOpFailed->execute([
                    ":ref" => $reference,
                    ":code" => $code,
                    ":op_id" => $clientOperationId
                ]);
            }

            Response::json(false, $message, array_merge([
                "_is_custom_payload" => true,
                "code" => $code,
                "reference" => $reference
            ], $extraData), $statusCode);
        }
    }

    /**
     * GET /operacoes/{client_operation_id}/status
     */
    public function checkStatus(string $clientOperationId): void {
        Auth::requireAuth(["ADMINISTRADOR", "TECNICO_SST", "ALMOXARIFE_OPERADOR"]);
        
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM operacoes_idempotentes WHERE ope_client_operation_id = :op_id LIMIT 1");
        $stmt->execute([":op_id" => $clientOperationId]);
        $op = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$op) {
            Response::json(false, "Operacao nao encontrada.", null, 404);
        }

        if ($op["ope_status"] === "CONCLUIDA") {
            $respData = json_decode($op["ope_resposta_json"], true);
            Response::json(true, "Operacao concluida.", [
                "_is_custom_payload" => true,
                "status" => "CONCLUIDA",
                "data" => $respData
            ]);
        } elseif ($op["ope_status"] === "FALHOU") {
            Response::json(true, "Operacao falhou.", [
                "_is_custom_payload" => true,
                "status" => "FALHOU",
                "error" => $op["ope_erro_referencia"]
            ]);
        } else {
            Response::json(true, "Operacao em processamento.", [
                "_is_custom_payload" => true,
                "status" => "PROCESSANDO"
            ]);
        }
    }
}
