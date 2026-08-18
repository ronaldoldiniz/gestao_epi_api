<?php
declare(strict_types=1);

namespace Security;

use Core\Auth;
use Core\Response;

class AuthorizationService {
    /**
     * Perfis válidos do sistema
     */
    private const PERFIS_VALIDOS = [
        'ADMINISTRADOR',
        'RH_ADMINISTRATIVO',
        'TECNICO_SST',
        'ALMOXARIFE_OPERADOR',
        'GESTOR'
    ];

    /**
     * Verifica se o usuário atual logado possui um dos perfis exigidos
     */
    public static function requireRole(array $allowedRoles): array {
        $currentUser = Auth::requireAuth();
        $perfil = $currentUser['usu_perfil'] ?? null;

        if (!$perfil || !in_array($perfil, $allowedRoles, true)) {
            Response::json(false, "Acesso negado. Perfil de usuário sem autorização para esta funcionalidade.", null, 403);
        }

        return $currentUser;
    }

    /**
     * Determina se o perfil atual tem acesso aos dados confidenciais puros (sem mascaramento)
     */
    public static function canViewSensitiveData(string $perfil): bool {
        return in_array($perfil, ['ADMINISTRADOR', 'RH_ADMINISTRATIVO'], true);
    }

    /**
     * Aplica mascaramento de segurança de acordo com o perfil de privilégios do usuário logado
     */
    public static function formatFuncionarioData(array $funcionario, string $currentUserRole): array {
        if (self::canViewSensitiveData($currentUserRole)) {
            // Retorna dados puros e completos
            return $funcionario;
        }

        // Aplica mascaramento para os perfis comuns (SST, Almoxarife, Gestor)
        if (isset($funcionario['fun_cpf']) && $funcionario['fun_cpf'] !== '') {
            $funcionario['fun_cpf'] = self::maskCpf($funcionario['fun_cpf']);
        }
        if (isset($funcionario['fun_esocial']) && $funcionario['fun_esocial'] !== '') {
            $funcionario['fun_esocial'] = self::maskESocial($funcionario['fun_esocial']);
        }

        return $funcionario;
    }

    /**
     * Mascara o CPF: 123.456.789-01 -> ***.***.***-01
     */
    public static function maskCpf(string $cpf): string {
        $clean = preg_replace('/\D/', '', $cpf);
        if (strlen($clean) !== 11) {
            return '***.***.***-XX';
        }
        return '***.***.***-' . substr($clean, -2);
    }

    /**
     * Mascara o eSocial: ESO12345678 -> ***.***.5678
     */
    public static function maskESocial(string $esocial): string {
        if (strlen($esocial) < 4) {
            return '***.***.****';
        }
        return '***.***.' . substr($esocial, -4);
    }
}
