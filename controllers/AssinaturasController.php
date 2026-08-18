<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Core\Audit;
use Models\AssinaturaEletronica;
use Models\Funcionario;
use Exception;

class AssinaturasController {
    private AssinaturaEletronica $assinaturaModel;
    private Funcionario $funcionarioModel;

    public function __construct() {
        $this->assinaturaModel = new AssinaturaEletronica();
        $this->funcionarioModel = new Funcionario();
    }

    /**
     * POST /assinaturas
     */
    public function store(): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'ALMOXARIFE_OPERADOR']);
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['fun_id']) || !isset($input['pin'])) {
            Response::json(false, "O ID do funcionário e o PIN são obrigatórios.", null, 422);
        }

        $funId = (int)$input['fun_id'];
        $pin = trim((string)$input['pin']);

        if (strlen($pin) < 4 || strlen($pin) > 10) {
            Response::json(false, "O PIN de segurança deve possuir entre 4 e 10 caracteres.", null, 422);
        }

        // Valida existência do funcionário
        $funcionario = $this->funcionarioModel->findById($funId, false);
        if (!$funcionario) {
            Response::json(false, "Funcionário não encontrado.", null, 404);
        }

        $db = \Config\Database::getConnection();

        try {
            $db->beginTransaction();

            $existingAssinatura = $this->assinaturaModel->findByFuncionarioId($funId);
            if ($existingAssinatura) {
                $assId = (int)$existingAssinatura['ass_id'];
                $this->assinaturaModel->updatePin($assId, $pin);
                $acaoLog = "REDEFINIÇÃO_DE_SENHA";
                $detalhesLog = json_encode(['ocorrencia' => 'PIN de assinatura eletrônica redefinido.'], JSON_UNESCAPED_UNICODE);
            } else {
                $assId = $this->assinaturaModel->create([
                    'fun_id' => $funId,
                    'usu_id' => (int)$currentUser['usu_id'],
                    'pin' => $pin
                ]);
                $acaoLog = "CADASTRO";
                $detalhesLog = json_encode(['ocorrencia' => 'PIN de assinatura eletrônica cadastrado para o funcionário: ' . $funcionario['fun_nome']], JSON_UNESCAPED_UNICODE);
            }

            $this->funcionarioModel->markAsActiveAfterSignature($funId);

            Audit::log($acaoLog, "Assinatura_Eletronica", $assId, $detalhesLog, null, $funId, null, null, null, $assId);
            
            $db->commit();
            Response::json(true, "Assinatura eletrônica cadastrada/confirmada com sucesso.", ['ass_id' => $assId], 201);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Response::json(false, "Falha ao processar assinatura eletrônica: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * PUT /assinaturas/{id} (Alterar PIN diretamente sabendo o ID da assinatura)
     */
    public function update(string $id): void {
        Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'ALMOXARIFE_OPERADOR']);
        $assId = (int)$id;
        
        $assinatura = $this->assinaturaModel->findById($assId);
        if (!$assinatura) {
            Response::json(false, "Assinatura eletrônica não encontrada.", null, 404);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['pin']) || trim((string)$input['pin']) === '') {
            Response::json(false, "O PIN é obrigatório.", null, 422);
        }

        $newPin = trim((string)$input['pin']);
        if (strlen($newPin) < 4 || strlen($newPin) > 10) {
            Response::json(false, "O PIN deve possuir entre 4 e 10 caracteres.", null, 422);
        }

        $db = \Config\Database::getConnection();
        try {
            $db->beginTransaction();

            $this->assinaturaModel->updatePin($assId, $newPin);
            
            $this->funcionarioModel->markAsActiveAfterSignature((int)$assinatura['fun_id']);

            $detalhesLog = json_encode(['ocorrencia' => 'PIN de assinatura eletrônica redefinido.'], JSON_UNESCAPED_UNICODE);
            Audit::log("REDEFINIÇÃO_DE_SENHA", "Assinatura_Eletronica", $assId, $detalhesLog, null, (int)$assinatura['fun_id'], null, null, null, $assId);
            
            $db->commit();
            Response::json(true, "PIN de assinatura eletrônica atualizado com sucesso.");
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Response::json(false, "Falha ao atualizar PIN: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /assinaturas/validar
     */
    public function validar(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR']);
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['fun_id']) || !isset($input['pin'])) {
            Response::json(false, "O ID do funcionário e o PIN são obrigatórios.", null, 422);
        }

        $funId = (int)$input['fun_id'];
        $pin = trim((string)$input['pin']);

        $assinatura = $this->assinaturaModel->findByFuncionarioId($funId);
        if (!$assinatura) {
            Response::json(false, "Assinatura eletrônica não cadastrada para este funcionário.", null, 404);
        }

        if ($assinatura['ass_status'] === 'BLOQUEADO') {
            $detalhesLog = json_encode(['ocorrencia' => 'Tentativa de uso de assinatura eletrônica bloqueada.'], JSON_UNESCAPED_UNICODE);
            Audit::log("VALIDAÇÃO", "Assinatura_Eletronica", (int)$assinatura['ass_id'], $detalhesLog, null, $funId, null, null, null, (int)$assinatura['ass_id']);
            Response::json(false, "A assinatura eletrônica deste funcionário está BLOQUEADA por excesso de erros. Solicite o desbloqueio ao RH.", null, 403);
        }

        if ($assinatura['ass_status'] !== 'ATIVO') {
            Response::json(false, "A assinatura eletrônica do funcionário não está ativa no momento.", null, 403);
        }

        $configFile = dirname(__DIR__) . '/config/config.php';
        if (!file_exists($configFile)) {
            $configFile = dirname(__DIR__) . '/config/config.example.php';
        }
        $config = require $configFile;
        $maxPinAttempts = $config['security']['max_pin_attempts'] ?? 3;

        if (\Security\PasswordService::verify($pin, $assinatura['ass_salt'] ?? '', $assinatura['ass_senha_hash'])) {
            $this->assinaturaModel->registerUse((int)$assinatura['ass_id']);
            $detalhesLog = json_encode(['ocorrencia' => 'Validação de PIN bem-sucedida.'], JSON_UNESCAPED_UNICODE);
            Audit::log("VALIDAÇÃO", "Assinatura_Eletronica", (int)$assinatura['ass_id'], $detalhesLog, null, $funId, null, null, null, (int)$assinatura['ass_id']);
            
            Response::json(true, "PIN validado com sucesso.", [
                'ass_id' => (int)$assinatura['ass_id'],
                'fun_id' => $funId
            ]);
        } else {
            $attempts = $this->assinaturaModel->incrementFailAttempts((int)$assinatura['ass_id'], $maxPinAttempts);
            
            $detalhesLog = json_encode(['ocorrencia' => "Falha na validação do PIN. Tentativa {$attempts} de {$maxPinAttempts}."], JSON_UNESCAPED_UNICODE);
            Audit::log("VALIDAÇÃO", "Assinatura_Eletronica", (int)$assinatura['ass_id'], $detalhesLog, null, $funId, null, null, null, (int)$assinatura['ass_id']);

            if ($attempts >= $maxPinAttempts) {
                Response::json(false, "PIN incorreto. A assinatura eletrônica do funcionário foi BLOQUEADA por excesso de erros.", null, 401);
            } else {
                $restantes = $maxPinAttempts - $attempts;
                Response::json(false, "PIN incorreto. O funcionário possui mais {$restantes} tentativa(s) antes do bloqueio.", null, 401);
            }
        }
    }

    /**
     * POST /assinaturas/redefinir
     */
    public function redefinir(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'ALMOXARIFE_OPERADOR']);
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['fun_id']) || !isset($input['pin'])) {
            Response::json(false, "O ID do funcionário e o PIN são obrigatórios.", null, 422);
        }

        $funId = (int)$input['fun_id'];
        $newPin = trim((string)$input['pin']);

        if (strlen($newPin) < 4 || strlen($newPin) > 10) {
            Response::json(false, "O PIN deve possuir entre 4 e 10 caracteres.", null, 422);
        }

        $assinatura = $this->assinaturaModel->findByFuncionarioId($funId);
        if (!$assinatura) {
            Response::json(false, "Assinatura eletrônica não cadastrada para este funcionário.", null, 404);
        }

        $db = \Config\Database::getConnection();
        try {
            $db->beginTransaction();

            $this->assinaturaModel->updatePin((int)$assinatura['ass_id'], $newPin);
            
            $this->funcionarioModel->markAsActiveAfterSignature($funId);

            $detalhesLog = json_encode(['ocorrencia' => 'PIN de assinatura eletrônica redefinido.'], JSON_UNESCAPED_UNICODE);
            Audit::log("REDEFINIÇÃO_DE_SENHA", "Assinatura_Eletronica", (int)$assinatura['ass_id'], $detalhesLog, null, $funId, null, null, null, (int)$assinatura['ass_id']);
            
            $db->commit();
            Response::json(true, "PIN de assinatura eletrônica do funcionário redefinido com sucesso.");
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Response::json(false, "Falha ao redefinir PIN: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /assinaturas/bloquear/{id}
     */
    public function bloquear(string $id): void {
        Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST']);
        $assId = (int)$id;

        $assinatura = $this->assinaturaModel->findById($assId);
        if (!$assinatura) {
            Response::json(false, "Assinatura eletrônica não encontrada.", null, 404);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $motivo = $input['motivo'] ?? "Bloqueio manual realizado por operador autorizado.";

        try {
            $this->assinaturaModel->lockSignature($assId, $motivo);
            $detalhesLog = json_encode(['ocorrencia' => "Assinatura eletrônica bloqueada. Motivo: " . $motivo], JSON_UNESCAPED_UNICODE);
            Audit::log("BLOQUEIO", "Assinatura_Eletronica", $assId, $detalhesLog, null, (int)$assinatura['fun_id'], null, null, null, $assId);
            Response::json(true, "Assinatura eletrônica bloqueada com sucesso.");
        } catch (Exception $e) {
            Response::json(false, "Falha ao bloquear assinatura: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /assinaturas/desbloquear/{id}
     */
    public function desbloquear(string $id): void {
        Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'ALMOXARIFE_OPERADOR']);
        $assId = (int)$id;

        $assinatura = $this->assinaturaModel->findById($assId);
        if (!$assinatura) {
            Response::json(false, "Assinatura eletrônica não encontrada.", null, 404);
        }

        $db = \Config\Database::getConnection();
        try {
            $db->beginTransaction();

            $this->assinaturaModel->unlockSignature($assId);
            
            $this->funcionarioModel->markAsActiveAfterSignature((int)$assinatura['fun_id']);

            $detalhesLog = json_encode(['ocorrencia' => 'Assinatura eletrônica desbloqueada pelo operador.'], JSON_UNESCAPED_UNICODE);
            Audit::log("DESBLOQUEIO", "Assinatura_Eletronica", $assId, $detalhesLog, null, (int)$assinatura['fun_id'], null, null, null, $assId);
            
            $db->commit();
            Response::json(true, "Assinatura eletrônica desbloqueada com sucesso.");
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Response::json(false, "Falha ao desbloquear assinatura: " . $e->getMessage(), null, 500);
        }
    }
}
