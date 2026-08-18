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
        Auth::requireAuth(['ADMINISTRADOR']);
        
        $usuarios = $this->usuarioModel->findAll();
        $resultado = array_map(function($user) {
            return [
                'usu_id' => (int)$user['usu_id'],
                'usu_login' => $user['usu_login'],
                'usu_perfil' => $user['usu_perfil'],
                'usu_status' => $user['usu_status'],
                'usu_data_cadastro' => $user['usu_data_cadastro'],
                'usu_exige_troca_senha' => (int)($user['usu_exige_troca_senha'] ?? 0) === 1
            ];
        }, $usuarios);

        Response::json(true, "Usuários listados com sucesso.", $resultado);
    }

    /**
     * GET /usuarios/{id}
     */
    public function show(string $id): void {
        Auth::requireAuth(['ADMINISTRADOR']);
        
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
            'usu_exige_troca_senha' => (int)($usuario['usu_exige_troca_senha'] ?? 0) === 1
        ];

        Response::json(true, "Usuário localizado com sucesso.", $resultado);
    }

    /**
     * POST /usuarios
     */
    public function store(): void {
        Auth::requireAuth(['ADMINISTRADOR']);
        
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
            $userId = $this->usuarioModel->create([
                'usu_login' => $login,
                'senha' => $senha,
                'usu_perfil' => $perfil,
                'usu_status' => $input['usu_status'] ?? 'ATIVO',
                'usu_exige_troca_senha' => $exigirTroca ? 1 : 0
            ]);

            Audit::log("Criou um novo usuário", "Usuarios", $userId, "Login criado: " . $login);

            $novoUsuario = $this->usuarioModel->findById($userId);
            $resultado = [
                'usu_id' => (int)$novoUsuario['usu_id'],
                'usu_login' => $novoUsuario['usu_login'],
                'usu_perfil' => $novoUsuario['usu_perfil'],
                'usu_status' => $novoUsuario['usu_status'],
                'usu_data_cadastro' => $novoUsuario['usu_data_cadastro'],
                'usu_exige_troca_senha' => (int)($novoUsuario['usu_exige_troca_senha'] ?? 0) === 1
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
        Auth::requireAuth(['ADMINISTRADOR']);
        
        $userId = (int)$id;
        $usuario = $this->usuarioModel->findById($userId);
        if (!$usuario) {
            Response::json(false, "Usuário não encontrado.", null, 404);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input)) {
            Response::json(false, "Nenhum dado informado para atualização.", null, 400);
        }

        // Valida perfil se informado
        if (isset($input['usu_perfil'])) {
            $perfisPermitidos = ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR'];
            if (!in_array($input['usu_perfil'], $perfisPermitidos, true)) {
                Response::json(false, "Perfil inválido especificado.", null, 422);
            }
        }

        // Verifica unicidade do login se alterado
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
            Audit::log("Atualizou dados de um usuário", "Usuarios", $userId, "Campos editados: " . implode(", ", array_keys($updatable)));

            $atualizado = $this->usuarioModel->findById($userId);
            $resultado = [
                'usu_id' => (int)$atualizado['usu_id'],
                'usu_login' => $atualizado['usu_login'],
                'usu_perfil' => $atualizado['usu_perfil'],
                'usu_status' => $atualizado['usu_status'],
                'usu_data_cadastro' => $atualizado['usu_data_cadastro'],
                'usu_exige_troca_senha' => (int)($atualizado['usu_exige_troca_senha'] ?? 0) === 1
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
        $currentUser = Auth::requireAuth(['ADMINISTRADOR']);
        
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
            Audit::log("Inativou um usuário (Exclusão lógica)", "Usuarios", $userId, "Login inativado: " . $usuario['usu_login']);
            Response::json(true, "Usuário inativado com sucesso.");
        } catch (Exception $e) {
            Response::json(false, "Falha ao inativar usuário: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /usuarios/{id}/redefinir-senha
     */
    public function redefinirSenha(string $id): void {
        Auth::requireAuth(['ADMINISTRADOR']);
        
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

            // Reseta tentativas de login falhas
            $this->usuarioModel->resetFailAttempts($usuario['usu_login']);

            Audit::log("Redefiniu a senha de um usuário", "Usuarios", $userId, "Senha redefinida temporariamente.");

            // Retorna dados atualizados sem o hash
            $atualizado = $this->usuarioModel->findById($userId);
            $resultado = [
                'usu_id' => (int)$atualizado['usu_id'],
                'usu_login' => $atualizado['usu_login'],
                'usu_perfil' => $atualizado['usu_perfil'],
                'usu_status' => $atualizado['usu_status'],
                'usu_data_cadastro' => $atualizado['usu_data_cadastro'],
                'usu_exige_troca_senha' => (int)($atualizado['usu_exige_troca_senha'] ?? 0) === 1
            ];

            Response::json(true, "Senha redefinida com sucesso.", $resultado);
        } catch (Exception $e) {
            Response::json(false, "Falha ao redefinir a senha: " . $e->getMessage(), null, 500);
        }
    }
}
