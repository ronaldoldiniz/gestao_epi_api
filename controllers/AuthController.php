<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Core\Audit;
use Models\Usuario;
use Exception;

class AuthController {
    private ?Usuario $usuarioModel = null;

    private function getUsuarioModel(): Usuario {
        if ($this->usuarioModel === null) {
            $this->usuarioModel = new Usuario();
        }
        return $this->usuarioModel;
    }

    /**
     * POST /auth/login
     */
    public function login(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['usu_login']) || !isset($input['senha'])) {
            Response::json(false, "Usuário e senha são obrigatórios.", null, 400);
        }

        $login = trim($input['usu_login']);
        $senha = $input['senha'];

        // Carrega configurações de segurança
        $configFile = dirname(__DIR__) . '/config/config.php';
        if (!file_exists($configFile)) {
            $configFile = dirname(__DIR__) . '/config/config.example.php';
        }
        $config = require $configFile;

        $maxAttempts = $config['security']['max_login_attempts'] ?? 5;
        $lockoutTime = $config['security']['lockout_time'] ?? 900;
        $secretKey = $config['app']['secret_key'];
        $tokenTtl = $config['app']['token_ttl'] ?? 43200;

        $user = $this->getUsuarioModel()->findByLogin($login);

        if (!$user || $user['usu_status'] !== 'ATIVO') {
            // Retorna erro genérico por privacidade
            Response::json(false, "Usuário ou senha incorretos.", null, 401);
        }

        // Verifica bloqueio de conta
        if ($this->getUsuarioModel()->isLocked($user, $lockoutTime)) {
            $bloqueioAte = date('H:i:s', strtotime($user['usu_data_bloqueio']) + $lockoutTime);
            $detalhesLog = json_encode(['ocorrencia' => "Tentativa de login em conta bloqueada para o usuário: " . $login], JSON_UNESCAPED_UNICODE);
            Audit::log("LOGIN", "Usuarios", (int)$user['usu_id'], $detalhesLog);
            Response::json(false, "Esta conta está temporariamente bloqueada devido a excesso de tentativas. Tente novamente após as " . $bloqueioAte . ".", null, 403);
        }

        // Valida senha usando SHA-256 + Salt
        if (\Security\PasswordService::verify($senha, $user['usu_senha_salt'] ?? '', $user['usu_senha_hash'])) {
            // Zera falhas se existirem
            if ((int)$user['usu_tentativas_falha'] > 0) {
                $this->getUsuarioModel()->resetFailAttempts($login);
            }

            // Gera token
            $token = Auth::generateToken($user, $secretKey, $tokenTtl);

            // Log de auditoria do sucesso
            $detalhesLog = json_encode(['ocorrencia' => "Login realizado com sucesso."], JSON_UNESCAPED_UNICODE);
            Audit::log("LOGIN", "Usuarios", (int)$user['usu_id'], $detalhesLog, (int)$user['usu_id']);

            $exigeTroca = (int)($user['usu_exige_troca_senha'] ?? 0) === 1;
            $msg = $exigeTroca ? "Login realizado com sucesso. Troca de senha obrigatória." : "Login realizado com sucesso.";

            Response::json(true, $msg, [
                'token' => $token,
                'exige_troca_senha' => $exigeTroca,
                'usuario' => [
                    'usu_id' => (int)$user['usu_id'],
                    'usu_login' => $user['usu_login'],
                    'usu_perfil' => $user['usu_perfil'],
                    'usu_status' => $user['usu_status'],
                    'usu_aceite_termos' => (int)($user['usu_aceite_termos'] ?? 0) === 1,
                    'usu_data_aceite_termos' => isset($user['usu_data_aceite_termos']) ? (int)$user['usu_data_aceite_termos'] : null
                ]
            ]);
        } else {
            // Senha incorreta. Incrementa tentativas falhas
            $attempts = $this->getUsuarioModel()->incrementFailAttempts($login, $maxAttempts);
            
            // Log de auditoria da falha
            $detalhesLog = json_encode(['ocorrencia' => "Tentativa de login com senha incorreta. Tentativa {$attempts} de {$maxAttempts}."], JSON_UNESCAPED_UNICODE);
            Audit::log("LOGIN", "Usuarios", (int)$user['usu_id'], $detalhesLog);

            if ($attempts >= $maxAttempts) {
                Response::json(false, "Usuário ou senha incorretos. A conta foi bloqueada temporariamente por 15 minutos.", null, 401);
            } else {
                $restantes = $maxAttempts - $attempts;
                Response::json(false, "Usuário ou senha incorretos. Você tem mais {$restantes} tentativa(s) antes do bloqueio.", null, 401);
            }
        }
    }

    /**
     * POST /auth/logout
     */
    public function logout(): void {
        $currentUser = Auth::requireAuth();
        $detalhesLog = json_encode(['ocorrencia' => "Logout realizado pelo usuário."], JSON_UNESCAPED_UNICODE);
        Audit::log("LOGOUT", "Usuarios", (int)$currentUser['usu_id'], $detalhesLog);
        Response::json(true, "Logout realizado com sucesso.");
    }

    /**
     * GET /auth/me
     */
    public function me(): void {
        $currentUser = Auth::requireAuth();

        $user = $this->getUsuarioModel()->findById((int)$currentUser['usu_id']);
        $aceiteTermos = $user ? (int)($user['usu_aceite_termos'] ?? 0) === 1 : false;
        $dataAceiteTermos = $user && isset($user['usu_data_aceite_termos']) ? (int)$user['usu_data_aceite_termos'] : null;

        Response::json(true, "Informações do usuário autenticado.", [
            'usuario' => [
                'usu_id' => (int)$currentUser['usu_id'],
                'usu_login' => $currentUser['usu_login'],
                'usu_perfil' => $currentUser['usu_perfil'],
                'usu_aceite_termos' => $aceiteTermos,
                'usu_data_aceite_termos' => $dataAceiteTermos
            ]
        ]);
    }

    /**
     * POST /auth/alterar-senha-primeiro-acesso
     */
    public function alterarSenhaPrimeiroAcesso(): void {
        // Exige autenticação (sem passar perfil específico)
        $currentUser = Auth::requireAuth();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['senha_atual']) || !isset($input['nova_senha']) || !isset($input['confirmar_senha'])) {
            Response::json(false, "Todos os campos de senha são obrigatórios.", null, 400);
        }

        $senhaAtual = $input['senha_atual'];
        $novaSenha = $input['nova_senha'];
        $confirmarSenha = $input['confirmar_senha'];

        // Carrega usuário completo do banco
        $user = $this->getUsuarioModel()->findByLogin($currentUser['usu_login']);
        if (!$user) {
            Response::json(false, "Usuário não encontrado.", null, 404);
        }

        // Valida senha atual usando SHA-256 + Salt
        if (!\Security\PasswordService::verify($senhaAtual, $user['usu_senha_salt'] ?? '', $user['usu_senha_hash'])) {
            Response::json(false, "A senha atual informada está incorreta.", null, 400);
        }

        // Valida nova_senha e confirmar_senha
        if ($novaSenha !== $confirmarSenha) {
            Response::json(false, "A nova senha e a confirmação de senha não coincidem.", null, 400);
        }

        // Valida política de senha
        if (strlen($novaSenha) < 6) {
            Response::json(false, "A nova senha deve ter no mínimo 6 caracteres.", null, 400);
        }
        if (!preg_match('/[a-zA-Z]/', $novaSenha) || !preg_match('/[0-9]/', $novaSenha)) {
            Response::json(false, "A nova senha deve conter pelo menos uma letra e um número.", null, 400);
        }

        try {
            // Atualiza senha, zera exigência de troca
            $this->getUsuarioModel()->update((int)$user['usu_id'], [
                'senha' => $novaSenha,
                'usu_exige_troca_senha' => 0
            ]);

            // Reseta tentativas de login falhas
            $this->getUsuarioModel()->resetFailAttempts($user['usu_login']);

            $detalhesLog = json_encode(['ocorrencia' => "Senha do usuário redefinida."], JSON_UNESCAPED_UNICODE);
            Audit::log("REDEFINIÇÃO_DE_SENHA", "Usuarios", (int)$user['usu_id'], $detalhesLog);

            Response::json(true, "Senha alterada com sucesso.");
        } catch (Exception $e) {
            Response::json(false, "Erro ao alterar a senha: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /auth/recuperar-senha
     *
     * Redefine a senha de um usuário no fluxo público de recuperação de senha.
     * O usuário não precisa estar autenticado: a identidade é informada no corpo.
     */
    public function recuperarSenha(): void {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['usu_login']) || !isset($input['nova_senha']) || !isset($input['confirmar_senha'])) {
            Response::json(false, "Usuário, nova senha e confirmação são obrigatórios.", null, 400);
        }

        $login = trim($input['usu_login']);
        $novaSenha = $input['nova_senha'];
        $confirmarSenha = $input['confirmar_senha'];

        if ($login === '') {
            Response::json(false, "Informe o usuário.", null, 400);
        }

        $user = $this->getUsuarioModel()->findByLogin($login);
        if (!$user) {
            Response::json(false, "Usuário não encontrado.", null, 404);
        }

        if ($user['usu_status'] !== 'ATIVO') {
            Response::json(false, "Este usuário está inativo e não pode ter a senha recuperada.", null, 403);
        }

        if ($novaSenha !== $confirmarSenha) {
            Response::json(false, "A nova senha e a confirmação de senha não coincidem.", null, 400);
        }

        if (strlen($novaSenha) < 6) {
            Response::json(false, "A nova senha deve ter no mínimo 6 caracteres.", null, 400);
        }
        if (!preg_match('/[a-zA-Z]/', $novaSenha) || !preg_match('/[0-9]/', $novaSenha)) {
            Response::json(false, "A nova senha deve conter pelo menos uma letra e um número.", null, 400);
        }

        try {
            $this->getUsuarioModel()->update((int)$user['usu_id'], [
                'senha' => $novaSenha,
                'usu_exige_troca_senha' => 0
            ]);

            $this->getUsuarioModel()->resetFailAttempts($user['usu_login']);

            $detalhesLog = json_encode(['ocorrencia' => "Senha redefinida pelo fluxo de recuperação de senha para o usuário: " . $user['usu_login']], JSON_UNESCAPED_UNICODE);
            Audit::log("REDEFINIÇÃO_DE_SENHA", "Usuarios", (int)$user['usu_id'], $detalhesLog);

            Response::json(true, "Senha redefinida com sucesso. Utilize a nova senha para acessar o sistema.");
        } catch (Exception $e) {
            Response::json(false, "Erro ao redefinir a senha: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /health
     */
    public function health(): void {
        Response::json(true, "API Gestão EPI online.");
    }
}
