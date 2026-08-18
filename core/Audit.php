<?php
declare(strict_types=1);

namespace Core;

use Config\Database;
use PDO;
use Exception;

class Audit {
    /**
     * Registra um evento de auditoria no banco de dados.
     * Retorna true em caso de sucesso e false em caso de falha.
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
        try {
            $db = Database::getConnection();

            // Se o usuId não for passado explicitamente, tenta capturar o usuário atualmente autenticado
            if ($usuId === null) {
                $currentUser = Auth::getCurrentUser();
                if ($currentUser) {
                    $usuId = (int)$currentUser['usu_id'];
                }
            }

            $sql = "INSERT INTO Log_Auditoria (
                        usu_id, fun_id, epi_id, entr_id, item_id, ass_id, hist_id,
                        log_acao, log_datahora, log_tabela, log_registro_id, log_detalhes
                    ) VALUES (
                        :usu_id, :fun_id, :epi_id, :entr_id, :item_id, :ass_id, :hist_id,
                        :log_acao, NOW(), :log_tabela, :log_registro_id, :log_detalhes
                    )";

            $stmt = $db->prepare($sql);
            return $stmt->execute([
                ':usu_id' => $usuId,
                ':fun_id' => $funId,
                ':epi_id' => $epiId,
                ':entr_id' => $entrId,
                ':item_id' => $itemId,
                ':ass_id' => $assId,
                ':hist_id' => $histId,
                ':log_acao' => $acao,
                ':log_tabela' => $tabela,
                ':log_registro_id' => $registroId,
                ':log_detalhes' => $detalhes
            ]);
        } catch (Exception $e) {
            // Falha na gravação do log não deve interromper o fluxo da aplicação principal,
            // então registramos no log de erro padrão do Apache/PHP e retornamos false
            error_log("Erro ao gravar Log_Auditoria: " . $e->getMessage());
            return false;
        }
    }
}
