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

        if (strlen($pin) < 4) {
            Response::json(false, "O PIN de segurança deve possuir pelo menos 4 dígitos.", null, 422);
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
                // Se já existir, atualiza o PIN e reativa
                $assId = (int)$existingAssinatura['ass_id'];
                $this->assinaturaModel->updatePin($assId, $pin);
                $acaoLog = "Redefiniu PIN de assinatura";
                $detalhesLog = "PIN atualizado e assinatura confirmada como ativa";
            } else {
                // Cria nova assinatura eletrônica
                $assId = $this->assinaturaModel->create([
                    'fun_id' => $funId,
                    'usu_id' => (int)$currentUser['usu_id'],
                    'pin' => $pin
                ]);
                $acaoLog = "Criou assinatura eletrônica";
                $detalhesLog = "Assinatura vinculada ao funcionário: " . $funcionario['fun_nome'];
            }

            // Sincroniza o status do funcionário para ATIVO
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
        if (strlen($newPin) < 4) {
            Response::json(false, "O PIN deve possuir pelo menos 4 caracteres.", null, 422);
        }

        $db = \Config\Database::getConnection();
        try {
            $db->beginTransaction();

            $this->assinaturaModel->updatePin($assId, $newPin);
            
            // Sincroniza o status do funcionário para ATIVO
            $this->funcionarioModel->markAsActiveAfterSignature((int)$assinatura['fun_id']);

            Audit::log("Redefiniu PIN de assinatura", "Assinatura_Eletronica", $assId, "PIN atualizado pelo administrador", null, (int)$assinatura['fun_id'], null, null, null, $assId);
            
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
            Audit::log("Tentativa de uso de assinatura bloqueada", "Assinatura_Eletronica", (int)$assinatura['ass_id'], "PIN inválido ou assinatura bloqueada", null, $funId, null, null, null, (int)$assinatura['ass_id']);
            Response::json(false, "A assinatura eletrônica deste funcionário está BLOQUEADA por excesso de erros. Solicite o desbloqueio ao RH.", null, 403);
        }

        if ($assinatura['ass_status'] !== 'ATIVO') {
            Response::json(false, "A assinatura eletrônica do funcionário não está ativa no momento.", null, 403);
        }

        // Carrega limites do config
        $configFile = dirname(__DIR__) . '/config/config.php';
        if (!file_exists($configFile)) {
            $configFile = dirname(__DIR__) . '/config/config.example.php';
        }
        $config = require $configFile;
        $maxPinAttempts = $config['security']['max_pin_attempts'] ?? 3;

        // Valida hash do PIN
        if (password_verify($pin, $assinatura['ass_senha_hash'])) {
            // Sucesso
            $this->assinaturaModel->registerUse((int)$assinatura['ass_id']);
            Audit::log("Validação de PIN bem-sucedida", "Assinatura_Eletronica", (int)$assinatura['ass_id'], "Funcionário validou assinatura com sucesso", null, $funId, null, null, null, (int)$assinatura['ass_id']);
            
            Response::json(true, "PIN validado com sucesso.", [
                'ass_id' => (int)$assinatura['ass_id'],
                'fun_id' => $funId
            ]);
        } else {
            // Falha
            $attempts = $this->assinaturaModel->incrementFailAttempts((int)$assinatura['ass_id'], $maxPinAttempts);
            
            Audit::log("Falha na validação do PIN", "Assinatura_Eletronica", (int)$assinatura['ass_id'], "Erro de digitação do PIN. Tentativa {$attempts} de {$maxPinAttempts}", null, $funId, null, null, null, (int)$assinatura['ass_id']);

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

        if (strlen($newPin) < 4) {
            Response::json(false, "O PIN deve possuir pelo menos 4 caracteres.", null, 422);
        }

        $assinatura = $this->assinaturaModel->findByFuncionarioId($funId);
        if (!$assinatura) {
            Response::json(false, "Assinatura eletrônica não cadastrada para este funcionário.", null, 404);
        }

        $db = \Config\Database::getConnection();
        try {
            $db->beginTransaction();

            $this->assinaturaModel->updatePin((int)$assinatura['ass_id'], $newPin);
            
            // Sincroniza o status do funcionário para ATIVO
            $this->funcionarioModel->markAsActiveAfterSignature($funId);

            Audit::log("Redefiniu PIN de assinatura", "Assinatura_Eletronica", (int)$assinatura['ass_id'], "PIN alterado", null, $funId, null, null, null, (int)$assinatura['ass_id']);
            
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
            Audit::log("Bloqueou assinatura eletrônica", "Assinatura_Eletronica", $assId, "Motivo: " . $motivo, null, (int)$assinatura['fun_id'], null, null, null, $assId);
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
            
            // Sincroniza o status do funcionário para ATIVO ao desbloquear
            $this->funcionarioModel->markAsActiveAfterSignature((int)$assinatura['fun_id']);

            Audit::log("Desbloqueou assinatura eletrônica", "Assinatura_Eletronica", $assId, "Desbloqueio efetuado pelo operador.", null, (int)$assinatura['fun_id'], null, null, null, $assId);
            
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
