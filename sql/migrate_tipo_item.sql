-- =====================================================================
-- MIGRAÇÃO: Adicionar suporte a Item de Segurança sem C.A.
-- Aplicar no banco db_gestao_epi via phpMyAdmin ou linha de comando
-- =====================================================================

-- 1. Remover índice único de epi_ca (não existente no MySQL, ignorado)


-- 2. Tornar epi_ca e epi_vencimento_ca opcionais
ALTER TABLE EPIs 
    MODIFY COLUMN epi_ca VARCHAR(30) NULL DEFAULT NULL,
    MODIFY COLUMN epi_vencimento_ca DATE NULL DEFAULT NULL;

-- 3. Adicionar novos campos de classificação e rastreabilidade
ALTER TABLE EPIs
    ADD COLUMN epi_tipo_item ENUM('EPI_COM_CA', 'ITEM_SEGURANCA_SEM_CA') NOT NULL DEFAULT 'EPI_COM_CA' AFTER epi_nome,
    ADD COLUMN epi_numero_lote VARCHAR(100) NULL DEFAULT NULL AFTER epi_vida_util_obs,
    ADD COLUMN epi_modelo VARCHAR(150) NULL DEFAULT NULL AFTER epi_numero_lote,
    ADD COLUMN epi_identificacao VARCHAR(100) NULL DEFAULT NULL AFTER epi_modelo,
    ADD COLUMN epi_ref_fornecedor VARCHAR(150) NULL DEFAULT NULL AFTER epi_identificacao;
