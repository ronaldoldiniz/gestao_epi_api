<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Core\Auth;
use Core\Audit;
use Models\Funcionario;
use Exception;

class FuncionariosController {
    private Funcionario $funcionarioModel;

    public function __construct() {
        $this->funcionarioModel = new Funcionario();
    }

    /**
     * Define se a requisição atual exige mascaramento de dados pessoais (CPF/eSocial)
     */
    private function shouldMask(array $user): bool {
        $perfil = $user['usu_perfil'];
        // Apenas ADMINISTRADOR e RH_ADMINISTRATIVO podem ver os dados completos
        return !in_array($perfil, ['ADMINISTRADOR', 'RH_ADMINISTRATIVO'], true);
    }

    /**
     * GET /funcionarios
     */
    public function index(): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        $mask = $this->shouldMask($currentUser);

        $funcionarios = $this->funcionarioModel->findAll($mask);
        Response::json(true, "Funcionários listados com sucesso.", $funcionarios);
    }

    /**
     * GET /funcionarios/{id}
     */
    public function show(string $id): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        $mask = $this->shouldMask($currentUser);

        $funcionario = $this->funcionarioModel->findById((int)$id, $mask);
        if (!$funcionario) {
            Response::json(false, "Funcionário não encontrado.", null, 404);
        }

        Response::json(true, "Funcionário localizado com sucesso.", $funcionario);
    }

    /**
     * GET /funcionarios/qrcode/{codigo}
     */
    public function showByQrCode(string $codigo): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        $mask = $this->shouldMask($currentUser);

        $funcionario = $this->funcionarioModel->findByQrCode($codigo, $mask);
        if (!$funcionario) {
            Response::json(false, "Funcionário com QR Code fornecido não foi encontrado.", null, 404);
        }

        Response::json(true, "Funcionário localizado via QR Code.", $funcionario);
    }

    /**
     * POST /funcionarios
     */
    public function store(): void {
        Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO']);
        
        $input = json_decode(file_get_contents('php://input'), true);

        // Validação básica dos dados obrigatórios
        $required = ['fun_nome', 'fun_cpf', 'fun_esocial', 'fun_departamento', 'fun_cargo', 'fun_dataadmissao'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
                Response::json(false, "O campo '{$field}' é obrigatório.", null, 422);
            }
        }

        // Valida CPF (formato simples)
        $cpf = preg_replace('/[^0-9]/', '', $input['fun_cpf']);
        if (strlen($cpf) !== 11) {
            Response::json(false, "CPF informado é inválido. Deve possuir 11 dígitos numéricos.", null, 422);
        }

        // Gerar QR Code único caso não enviado (podemos gerar um UUID simples ou string baseada no CPF)
        $qrcode = $input['fun_qrcode'] ?? null;
        if (!$qrcode || trim($qrcode) === '') {
            $qrcode = 'EPI-' . $cpf . '-' . bin2hex(random_bytes(4));
        }

        try {
            $funcId = $this->funcionarioModel->create([
                'fun_nome' => trim($input['fun_nome']),
                'fun_cpf' => $cpf,
                'fun_esocial' => trim($input['fun_esocial']),
                'fun_departamento' => trim($input['fun_departamento']),
                'fun_cargo' => trim($input['fun_cargo']),
                'fun_dataadmissao' => $input['fun_dataadmissao'],
                'fun_situacao' => $input['fun_situacao'] ?? 'ATIVO',
                'fun_qrcode' => $qrcode
            ]);

            Audit::log("Cadastrou funcionário", "Funcionarios", $funcId, "Funcionário: " . $input['fun_nome'], null, $funcId);

            $novo = $this->funcionarioModel->findById($funcId, false);
            Response::json(true, "Funcionário cadastrado com sucesso.", $novo, 201);
        } catch (Exception $e) {
            Response::json(false, "Falha ao cadastrar funcionário: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * PUT /funcionarios/{id}
     */
    public function update(string $id): void {
        Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO']);
        
        $funcId = (int)$id;
        $funcionario = $this->funcionarioModel->findById($funcId, false);
        if (!$funcionario) {
            Response::json(false, "Funcionário não encontrado.", null, 404);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input)) {
            Response::json(false, "Nenhum dado informado para atualização.", null, 400);
        }

        // Se CPF for alterado, valida formato
        if (isset($input['fun_cpf'])) {
            $input['fun_cpf'] = preg_replace('/[^0-9]/', '', $input['fun_cpf']);
            if (strlen($input['fun_cpf']) !== 11) {
                Response::json(false, "CPF informado é inválido.", null, 422);
            }
        }

        try {
            $this->funcionarioModel->update($funcId, $input);
            
            // Gravação no log de auditoria
            Audit::log("Atualizou dados de funcionário", "Funcionarios", $funcId, "Campos editados: " . implode(", ", array_keys($input)), null, $funcId);

            $atualizado = $this->funcionarioModel->findById($funcId, false);
            Response::json(true, "Funcionário atualizado com sucesso.", $atualizado);
        } catch (Exception $e) {
            Response::json(false, "Falha ao atualizar funcionário: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * DELETE /funcionarios/{id} (Exclusão lógica)
     */
    public function destroy(string $id): void {
        Auth::requireAuth(['ADMINISTRADOR']);
        
        $funcId = (int)$id;
        $funcionario = $this->funcionarioModel->findById($funcId, false);
        if (!$funcionario) {
            Response::json(false, "Funcionário não encontrado.", null, 404);
        }

        try {
            $this->funcionarioModel->delete($funcId);
            Audit::log("Inativou funcionário (Exclusão lógica)", "Funcionarios", $funcId, "Nome: " . $funcionario['fun_nome'], null, $funcId);
            Response::json(true, "Funcionário inativado com sucesso.");
        } catch (Exception $e) {
            Response::json(false, "Falha ao inativar funcionário: " . $e->getMessage(), null, 500);
        }
    }
}
