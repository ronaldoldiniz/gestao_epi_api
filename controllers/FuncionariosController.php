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
     * GET /funcionarios
     */
    public function index(): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        $funcionarios = $this->funcionarioModel->findAll();
        
        $formattedList = array_map(function($f) use ($currentUser) {
            return \Security\AuthorizationService::formatFuncionarioData($f, $currentUser['usu_perfil']);
        }, $funcionarios);

        Response::json(true, "Funcionários listados com sucesso.", $formattedList);
    }

    /**
     * GET /funcionarios/{id}
     */
    public function show(string $id): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        
        $funcionario = $this->funcionarioModel->findById((int)$id);
        if (!$funcionario) {
            Response::json(false, "Funcionário não encontrado.", null, 404);
        }

        $formatted = \Security\AuthorizationService::formatFuncionarioData($funcionario, $currentUser['usu_perfil']);
        Response::json(true, "Funcionário localizado com sucesso.", $formatted);
    }

    /**
     * GET /funcionarios/qrcode/{codigo}
     */
    public function showByQrCode(string $codigo): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
        
        $funcionario = $this->funcionarioModel->findByQrCode($codigo);
        if (!$funcionario) {
            Response::json(false, "Funcionário com QR Code fornecido não foi encontrado.", null, 404);
        }

        $formatted = \Security\AuthorizationService::formatFuncionarioData($funcionario, $currentUser['usu_perfil']);
        Response::json(true, "Funcionário localizado via QR Code.", $formatted);
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

        // Verificar se CPF já existe
        if ($this->funcionarioModel->findByCpf($cpf)) {
            Response::json(false, "Já existe um funcionário cadastrado com este CPF.", null, 409);
            return;
        }

        $qrcode = $input['fun_qrcode'] ?? null;
        if (!$qrcode || trim($qrcode) === '') {
            $qrcode = 'EPI-' . $cpf . '-' . bin2hex(random_bytes(4));
        }

        $dataToCreate = [
            'fun_nome' => trim($input['fun_nome']),
            'fun_cpf' => $cpf,
            'fun_esocial' => trim($input['fun_esocial']),
            'fun_departamento' => trim($input['fun_departamento']),
            'fun_cargo' => trim($input['fun_cargo']),
            'fun_dataadmissao' => $input['fun_dataadmissao'],
            'fun_situacao' => $input['fun_situacao'] ?? 'ATIVO',
            'fun_qrcode' => $qrcode
        ];

        try {
            $funcId = $this->funcionarioModel->create($dataToCreate);

            \Core\Audit::logCadastro("Funcionarios", $funcId, $input['fun_nome'], $dataToCreate);

            $novo = $this->funcionarioModel->findById($funcId);
            $formatted = \Security\AuthorizationService::formatFuncionarioData($novo, $currentUser['usu_perfil']);
            Response::json(true, "Funcionário cadastrado com sucesso.", $formatted, 201);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '1062') !== false && strpos($msg, 'fun_cpf') !== false) {
                Response::json(false, "Já existe um funcionário cadastrado com este CPF.", null, 409);
            } elseif (strpos($msg, '1062') !== false && (strpos($msg, 'fun_qrcode') !== false || strpos($msg, 'qrcode') !== false)) {
                Response::json(false, "Já existe um funcionário cadastrado com este QR Code.", null, 409);
            } else {
                Response::json(false, "Não foi possível cadastrar este funcionário, pois já existe um funcionário com esses dados no sistema. Verifique o cadastro existente antes de continuar.", null, 409);
            }
        }
    }

    /**
     * PUT /funcionarios/{id}
     */
    public function update(string $id): void {
        $currentUser = Auth::requireAuth(['ADMINISTRADOR', 'RH_ADMINISTRATIVO']);
        
        $funcId = (int)$id;
        $funcionario = $this->funcionarioModel->findById($funcId, false);
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$funcionario) {
            try {
                $cpf = preg_replace('/[^0-9]/', '', $input['fun_cpf'] ?? $input['cpf'] ?? '');
                if (strlen($cpf) !== 11) {
                    $cpf = '00000000000';
                }

                $qrcode = $input['fun_qrcode'] ?? 'EPI-' . $cpf . '-' . bin2hex(random_bytes(4));

                $dataToCreate = [
                    'fun_nome' => trim($input['fun_nome'] ?? $input['nome'] ?? ''),
                    'fun_cpf' => $cpf,
                    'fun_esocial' => trim($input['fun_esocial'] ?? $input['esocial'] ?? ''),
                    'fun_departamento' => trim($input['fun_departamento'] ?? $input['departamento'] ?? ''),
                    'fun_cargo' => trim($input['fun_cargo'] ?? $input['cargo'] ?? ''),
                    'fun_dataadmissao' => $input['fun_dataadmissao'] ?? $input['dataAdmissao'] ?? date('Y-m-d'),
                    'fun_situacao' => $input['fun_situacao'] ?? $input['situacao'] ?? 'ATIVO',
                    'fun_qrcode' => $qrcode
                ];

                $this->funcionarioModel->createWithId($funcId, $dataToCreate);

                \Core\Audit::logCadastro("Funcionarios", $funcId, $dataToCreate['fun_nome'], $dataToCreate);
                $atualizado = $this->funcionarioModel->findById($funcId);
                Response::json(true, "Funcionário atualizado com sucesso (sincronizado).", $atualizado);
                return;
            } catch (Exception $e) {
                Response::json(false, "Falha ao criar funcionário inexistente: " . $e->getMessage(), null, 500);
                return;
            }
        }

        if (empty($input)) {
            Response::json(false, "Nenhum dado informado para atualização.", null, 400);
        }

        if (isset($input['fun_cpf'])) {
            $input['fun_cpf'] = preg_replace('/[^0-9]/', '', $input['fun_cpf']);
            if (strlen($input['fun_cpf']) !== 11) {
                Response::json(false, "CPF informado é inválido.", null, 422);
            }
        }

        try {
            $this->funcionarioModel->update($funcId, $input);
            
            // Gravação no log de auditoria via compareAndLog
            \Core\Audit::compareAndLog("ALTERAÇÃO", "Funcionarios", $funcId, $funcionario, $input, null, $funcId);

            $atualizado = $this->funcionarioModel->findById($funcId);
            $formatted = \Security\AuthorizationService::formatFuncionarioData($atualizado, $currentUser['usu_perfil']);
            Response::json(true, "Funcionário atualizado com sucesso.", $formatted);
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
        $funcionario = $this->funcionarioModel->findById($funcId);
        if (!$funcionario) {
            Response::json(false, "Funcionário não encontrado.", null, 404);
        }

        try {
            $this->funcionarioModel->delete($funcId);
            \Core\Audit::logInativacao("Funcionarios", $funcId, $funcionario['fun_nome']);
            Response::json(true, "Funcionário inativado com sucesso.");
        } catch (Exception $e) {
            Response::json(false, "Falha ao inativar funcionário: " . $e->getMessage(), null, 500);
        }
    }
}
