-- ==============================================================================
-- Migration SQL Versão 6: Idempotência, Suporte a Hashes Offline e Logs V2
-- Projeto: Gestão EPI (API PHP & Android App)
-- Data: 02/09/2026
-- ==============================================================================

-- 1. Garantia da Tabela de Operações Idempotentes para Retry Offline
CREATE TABLE IF NOT EXISTS operacoes_idempotentes (
    ope_id INT AUTO_INCREMENT PRIMARY KEY,
    ope_client_operation_id VARCHAR(64) NOT NULL UNIQUE,
    ope_tipo_operacao VARCHAR(32) NOT NULL,
    ope_usu_id INT NOT NULL,
    ope_status VARCHAR(20) NOT NULL DEFAULT 'EM_PROCESSAMENTO',
    ope_entrega_id INT NULL,
    ope_devolucao_id INT NULL,
    ope_erro_referencia VARCHAR(64) NULL,
    ope_codigo_resultado VARCHAR(64) NULL,
    ope_resposta_json LONGTEXT NULL,
    ope_data_hora_recebimento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ope_data_hora_conclusao DATETIME NULL,
    INDEX idx_ope_client_op_id (ope_client_operation_id),
    INDEX idx_ope_status (ope_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Garantia de Índices de Desempenho nos Logs de Auditoria
ALTER TABLE log_auditoria ADD INDEX IF NOT EXISTS idx_log_tabela_registro (log_tabela, log_registro_id);
ALTER TABLE log_auditoria ADD INDEX IF NOT EXISTS idx_log_acao (log_acao);
ALTER TABLE log_auditoria ADD INDEX IF NOT EXISTS idx_log_datahora (log_datahora);

-- 3. Documentação do Formato JSON V2 nos Logs de Auditoria (NR-06 / Validade Jurídica)
-- Cada registro em log_auditoria.log_detalhes contendo log_tabela = 'entrega_epis' deve seguir a estrutura:
-- {
--   "versao_log": 2,
--   "tipo_evento": "ENTREGA_FINALIZADA" | "ENTREGA_COM_DEVOLUCAO_VINCULADA",
--   "resultado": "SUCESSO",
--   "origem": "ONLINE" | "OFFLINE",
--   "device_id": "Identificação do Aparelho",
--   "usuario": { "id": int, "login": "string", "nome": "string", "perfil": "string" },
--   "funcionario": { "id": int, "nome": "string", "cpf": "string", "matricula": "string", "cargo": "string", "departamento": "string" },
--   "contexto": { "ip": "string", "origem": "string", "user_agent": "string" },
--   "entrega": { "id": int, "status": "FINALIZADA", "motivo_geral": "string", "data_finalizacao": "YYYY-MM-DD HH:MM:SS", "quantidade_itens": int, "quantidade_unidades": int, "assinatura_validada": true, "metodo_aceite": "PIN_ELETRONICO" },
--   "itens": [
--     {
--       "epi_id": int,
--       "nome_epi": "string",
--       "ca": "string",
--       "fabricante": "string",
--       "quantidade": int,
--       "tamanho": "string",
--       "lote": "string",
--       "motivo_codigo": "string",
--       "substituicao": {
--         "possui_vinculo": true | false,
--         "id_item_anterior": int,
--         "nome_epi_anterior": "string",
--         "motivo_devolucao": "string",
--         "condicao_devolucao": "string",
--         "destino_devolucao": "string"
--       }
--     }
--   ],
--   "ocorrencia": "Texto descritivo auditável"
-- }