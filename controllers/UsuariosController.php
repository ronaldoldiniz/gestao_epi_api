<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Core\Audit;
use Models\Usuario;
use Exception;

class UsuariosController {
    private Usuario $usuarioModel;

    public function __construct() {
        $this->usuarioModel = new Usuario();
    }

    /**
     * GET /usuarios
     */
    public function index(): void {
        $currentUser = Auth::requireAuth();
        if ($currentUser['usu_perfil'] !== 'ADMINISTRADOR') {
            Response::json(false, "Acesso negado. Esta funcionalidade é exclusiva do Administrador do Sistema.", null, 403);
        }
        
        $usuarios = $this->usuarioModel->findAll();
        $resultado = array_map(function($user) {
            return [
                'usu_id' => (int)$user['usu_id'],
                'usu_login' => $user['usu_login'],
                'usu_perfil' => $user['usu_perfil'],
                'usu_status' => $user['usu_status'],
                'usu_data_cadastro' => $user['usu_data_cadastro'],
                'usu_exige_troca_senha' => (int)($user['usu_exige_troca_senha'] ?? 0) === 1,
                'usu_ultimo_login' => $user['usu_ultimo_login'] ?? null,
                'usu_aceite_termos' => (int)($user['usu_aceite_termos'] ?? 0) === 1,
                'usu_data_aceite_termos' => isset($user['usu_data_aceite_termos']) ? (int)$user['usu_data_aceite_termos'] : null
            ];
        }, $usuarios);

        Response::json(true, "Usuários listados com sucesso.", $resultado);
    }

    /**
     * GET /usuarios/{id}
     */
    public function show(string $id): void {
        $currentUser = Auth::requireAuth();
        if ($currentUser['usu_perfil'] !== 'ADMINISTRADOR') {
            Response::json(false, "Acesso negado. Esta funcionalidade é exclusiva do Administrador do Sistema.", null, 403);
        }
        
        $usuario = $this->usuarioModel->findById((int)$id);
        if (!$usuario) {
            Response::json(false, "Usuário não encontrado.", null, 404);
        }

        $resultado = [
            'usu_id' => (int)$usuario['usu_id'],
            'usu_login' => $usuario['usu_login'],
            'usu_perfil' => $usuario['usu_perfil'],
            'usu_status' => $usuario['usu_status'],
            'usu_data_cadastro' => $usuario['usu_data_cadastro'],
            'usu_exige_troca_senha' => (int)($usuario['usu_exige_troca_senha'] ?? 0) === 1,
            'usu_ultimo_login' => $usuario['usu_ultimo_login'] ?? null,
            'usu_aceite_termos' => (int)($usuario['usu_aceite_termos'] ?? 0) === 1,
            'usu_data_aceite_termos' => isset($usuario['usu_data_aceite_termos']) ? (int)$usuario['usu_data_aceite_termos'] : null
        ];

        Response::json(true, "Usuário localizado com sucesso.", $resultado);
    }

    /**
     * POST /usuarios/{id}/aceitar-termos
     */
    public function aceitarTermos(string $id): void {
        $currentUser = Auth::requireAuth();

        $userId = (int)$id;
        if ((int)$currentUser['usu_id'] !== $userId && $currentUser['usu_perfil'] !== 'ADMINISTRADOR') {
            Response::json(false, "Acesso negado.", null, 403);
        }

        $usuario = $this->usuarioModel->findById($userId);
        if (!$usuario) {
            Response::json(false, "Usuário não encontrado.", null, 404);
        }

        try {
            $this->usuarioModel->aceitarTermos($userId);

            $detalhesLog = json_encode(['ocorrencia' => "Aceite dos Termos de Uso registrado."], JSON_UNESCAPED_UNICODE);
            \Core\Audit::log("ACEITE_TERMOS", "Usuarios", $userId, $detalhesLog, $userId);

            Response::json(true, "Termos de Uso aceitos com sucesso.");
        } catch (Exception $e) {
            Response::json(false, "Falha ao registrar aceite: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /usuarios
     */
    public function store(): void {
        $currentUser = Auth::requireAuth();
        if ($currentUser['usu_perfil'] !== 'ADMINISTRADOR') {
            Response::json(false, "Acesso negado. Esta funcionalidade é exclusiva do Administrador do Sistema.", null, 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);

        $senha = $input['senha_temporaria'] ?? $input['senha'] ?? null;
        if (!isset($input['usu_login']) || $senha === null || !isset($input['usu_perfil'])) {
            Response::json(false, "Os campos login, senha/senha_temporaria e perfil são obrigatórios.", null, 422);
        }

        $login = trim($input['usu_login']);
        $perfil = trim($input['usu_perfil']);

        // Valida perfis permitidos
        $perfisPermitidos = ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR'];
        if (!in_array($perfil, $perfisPermitidos, true)) {
            Response::json(false, "Perfil inválido especificado.", null, 422);
        }

        // Verifica se login já existe
        if ($this->usuarioModel->findByLogin($login)) {
            Response::json(false, "Este login de usuário já está cadastrado.", null, 409);
        }

        // Se senha_temporaria for usada ou exigir_troca_senha for passado como true
        $exigirTroca = true;
        if (isset($input['exigir_troca_senha'])) {
            $exigirTroca = (bool)$input['exigir_troca_senha'];
        }

        try {
            $userData = [
                'usu_login' => $login,
                'senha' => $senha,
                'usu_perfil' => $perfil,
                'usu_status' => $input['usu_status'] ?? 'ATIVO',
                'usu_exige_troca_senha' => $exigirTroca ? 1 : 0
            ];
            $userId = $this->usuarioModel->create($userData);

            \Core\Audit::logCadastro("Usuarios", $userId, $login, $userData);

            $novoUsuario = $this->usuarioModel->findById($userId);
            $resultado = [
                'usu_id' => (int)$novoUsuario['usu_id'],
                'usu_login' => $novoUsuario['usu_login'],
                'usu_perfil' => $novoUsuario['usu_perfil'],
                'usu_status' => $novoUsuario['usu_status'],
                'usu_data_cadastro' => $novoUsuario['usu_data_cadastro'],
                'usu_exige_troca_senha' => (int)($novoUsuario['usu_exige_troca_senha'] ?? 0) === 1,
                'usu_aceite_termos' => (int)($novoUsuario['usu_aceite_termos'] ?? 0) === 1,
                'usu_data_aceite_termos' => isset($novoUsuario['usu_data_aceite_termos']) ? (int)$novoUsuario['usu_data_aceite_termos'] : null
            ];
            Response::json(true, "Usuário cadastrado com sucesso.", $resultado, 201);
        } catch (Exception $e) {
            Response::json(false, "Falha ao cadastrar usuário: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * PUT /usuarios/{id}
     */
    public function update(string $id): void {
        $currentUser = Auth::requireAuth();
        if ($currentUser['usu_perfil'] !== 'ADMINISTRADOR') {
            Response::json(false, "Acesso negado. Esta funcionalidade é exclusiva do Administrador do Sistema.", null, 403);
        }
        
        $userId = (int)$id;
        $usuario = $this->usuarioModel->findById($userId);
        if (!$usuario) {
            Response::json(false, "Usuário não encontrado.", null, 404);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input)) {
            Response::json(false, "Nenhum dado informado para atualização.", null, 400);
        }

        if (isset($input['usu_perfil'])) {
            $perfisPermitidos = ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR'];
            if (!in_array($input['usu_perfil'], $perfisPermitidos, true)) {
                Response::json(false, "Perfil inválido especificado.", null, 422);
            }
        }

        if (isset($input['usu_login']) && $input['usu_login'] !== $usuario['usu_login']) {
            if ($this->usuarioModel->findByLogin($input['usu_login'])) {
                Response::json(false, "Este login de usuário já está em uso.", null, 409);
            }
        }

        $updatable = [];
        if (isset($input['usu_login'])) {
            $updatable['usu_login'] = trim($input['usu_login']);
        }
        if (isset($input['usu_perfil'])) {
            $updatable['usu_perfil'] = trim($input['usu_perfil']);
        }
        if (isset($input['usu_status'])) {
            $updatable['usu_status'] = trim($input['usu_status']);
        }
        if (isset($input['usu_exige_troca_senha'])) {
            $updatable['usu_exige_troca_senha'] = $input['usu_exige_troca_senha'] ? 1 : 0;
        } elseif (isset($input['exigir_troca_senha'])) {
            $updatable['usu_exige_troca_senha'] = $input['exigir_troca_senha'] ? 1 : 0;
        }

        try {
            $this->usuarioModel->update($userId, $updatable);

            // Determina a ação correspondente
            $acao = "ALTERAÇÃO";
            if (isset($updatable['usu_perfil']) && $updatable['usu_perfil'] !== $usuario['usu_perfil']) {
                $acao = "ALTERAÇÃO_DE_PERFIL";
            }
            if (isset($updatable['usu_status'])) {
                if ($updatable['usu_status'] === 'BLOQUEADO' && $usuario['usu_status'] !== 'BLOQUEADO') {
                    $acao = "BLOQUEIO";
                } elseif ($updatable['usu_status'] === 'ATIVO' && $usuario['usu_status'] === 'BLOQUEADO') {
                    $acao = "DESBLOQUEIO";
                } elseif ($updatable['usu_status'] === 'INATIVO' && $usuario['usu_status'] !== 'INATIVO') {
                    $acao = "INATIVAÇÃO";
                }
            }
            
            \Core\Audit::compareAndLog($acao, "Usuarios", $userId, $usuario, $updatable);

            $atualizado = $this->usuarioModel->findById($userId);
            $resultado = [
                'usu_id' => (int)$atualizado['usu_id'],
                'usu_login' => $atualizado['usu_login'],
                'usu_perfil' => $atualizado['usu_perfil'],
                'usu_status' => $atualizado['usu_status'],
                'usu_data_cadastro' => $atualizado['usu_data_cadastro'],
                'usu_exige_troca_senha' => (int)($atualizado['usu_exige_troca_senha'] ?? 0) === 1,
                'usu_aceite_termos' => (int)($atualizado['usu_aceite_termos'] ?? 0) === 1,
                'usu_data_aceite_termos' => isset($atualizado['usu_data_aceite_termos']) ? (int)$atualizado['usu_data_aceite_termos'] : null
            ];
            Response::json(true, "Usuário atualizado com sucesso.", $resultado);
        } catch (Exception $e) {
            Response::json(false, "Falha ao atualizar usuário: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * DELETE /usuarios/{id} (Exclusão lógica)
     */
    public function destroy(string $id): void {
        $currentUser = Auth::requireAuth();
        if ($currentUser['usu_perfil'] !== 'ADMINISTRADOR') {
            Response::json(false, "Acesso negado. Esta funcionalidade é exclusiva do Administrador do Sistema.", null, 403);
        }
        
        $userId = (int)$id;
        if ($userId === (int)$currentUser['usu_id']) {
            Response::json(false, "Não é permitido inativar seu próprio usuário.", null, 400);
        }

        $usuario = $this->usuarioModel->findById($userId);
        if (!$usuario) {
            Response::json(false, "Usuário não encontrado.", null, 404);
        }

        try {
            $this->usuarioModel->delete($userId);
            \Core\Audit::logInativacao("Usuarios", $userId, $usuario['usu_login']);
            Response::json(true, "Usuário inativado com sucesso.");
        } catch (Exception $e) {
            Response::json(false, "Falha ao inativar usuário: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /usuarios/{id}/redefinir-senha
     */
    public function redefinirSenha(string $id): void {
        $currentUser = Auth::requireAuth();
        if ($currentUser['usu_perfil'] !== 'ADMINISTRADOR') {
            Response::json(false, "Acesso negado. Esta funcionalidade é exclusiva do Administrador do Sistema.", null, 403);
        }
        
        $userId = (int)$id;
        $usuario = $this->usuarioModel->findById($userId);
        if (!$usuario) {
            Response::json(false, "Usuário não encontrado.", null, 404);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['senha_temporaria'])) {
            Response::json(false, "O campo senha_temporaria é obrigatório.", null, 422);
        }

        $exigirTroca = true;
        if (isset($input['exigir_troca_senha'])) {
            $exigirTroca = (bool)$input['exigir_troca_senha'];
        }

        try {
            $this->usuarioModel->update($userId, [
                'senha' => $input['senha_temporaria'],
                'usu_exige_troca_senha' => $exigirTroca ? 1 : 0
            ]);

            $this->usuarioModel->resetFailAttempts($usuario['usu_login']);

            $ocorrencia = "Senha do usuário redefinida.";
            $detalhesJson = json_encode(['ocorrencia' => $ocorrencia], JSON_UNESCAPED_UNICODE);
            \Core\Audit::log("REDEFINIÇÃO_DE_SENHA", "Usuarios", $userId, $detalhesJson);

            // Retorna dados atualizados sem o hash
            $atualizado = $this->usuarioModel->findById($userId);
            $resultado = [
                'usu_id' => (int)$atualizado['usu_id'],
                'usu_login' => $atualizado['usu_login'],
                'usu_perfil' => $atualizado['usu_perfil'],
                'usu_status' => $atualizado['usu_status'],
                'usu_data_cadastro' => $atualizado['usu_data_cadastro'],
                'usu_exige_troca_senha' => (int)($atualizado['usu_exige_troca_senha'] ?? 0) === 1,
                'usu_aceite_termos' => (int)($atualizado['usu_aceite_termos'] ?? 0) === 1,
                'usu_data_aceite_termos' => isset($atualizado['usu_data_aceite_termos']) ? (int)$atualizado['usu_data_aceite_termos'] : null
            ];

            Response::json(true, "Senha redefinida com sucesso.", $resultado);
        } catch (Exception $e) {
            Response::json(false, "Falha ao redefinir a senha: " . $e->getMessage(), null, 500);
        }
    }
}
