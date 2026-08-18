<?php
declare(strict_types=1);

namespace Security;

use Core\Audit;

class AuditService {
    /**
     * Encapsula o registro de logs de auditoria estruturado
     */
    public static function log(
        string $acao,
        string $tabela,
        ?int $registroId = null,
        ?string $detalhes = null,
        ?int $usuId = null,
        ?int $funId = null,
        ?int $epiId = null,
        ?int $entrId = null,
        ?int $itemId = null,
        ?int $assId = null,
        ?int $histId = null
    ): bool {
        return Audit::log($acao, $tabela, $registroId, $detalhes, $usuId, $funId, $epiId, $entrId, $itemId, $assId, $histId);
    }

    /**
     * Registra log de comparação e alteração de registros
     */
    public static function compareAndLog(
        string $acao,
        string $tabela,
        ?int $registroId,
        array $oldData,
        array $newData,
        ?int $usuId = null,
        ?int $funId = null,
        ?int $epiId = null,
        ?int $entrId = null,
        ?int $itemId = null,
        ?int $assId = null,
        ?int $histId = null
    ): bool {
        return Audit::compareAndLog($acao, $tabela, $registroId, $oldData, $newData, $usuId, $funId, $epiId, $entrId, $itemId, $assId, $histId);
    }

    /**
     * Registra log de novo cadastro
     */
    public static function logCadastro(
        string $tabela,
        int $registroId,
        string $identificacaoAmigavel,
        array $dados,
        ?int $usuId = null
    ): bool {
        return Audit::logCadastro($tabela, $registroId, $identificacaoAmigavel, $dados, $usuId);
    }

    /**
     * Registra log de inativação
     */
    public static function logInativacao(
        string $tabela,
        int $registroId,
        string $identificacaoAmigavel,
        ?int $usuId = null
    ): bool {
        return Audit::logInativacao($tabela, $registroId, $identificacaoAmigavel, $usuId);
    }
}
