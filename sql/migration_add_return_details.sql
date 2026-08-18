-- Migration: Adiciona colunas de detalhes da devolução e motivo da entrega em Itens_Entrega
-- Execute este script no banco db_gestao_epi

ALTER TABLE Itens_Entrega
    ADD COLUMN item_devolucao_motivo VARCHAR(50) NULL DEFAULT NULL AFTER item_tamanho,
    ADD COLUMN item_devolucao_condicao VARCHAR(50) NULL DEFAULT NULL AFTER item_devolucao_motivo,
    ADD COLUMN item_devolucao_destino VARCHAR(50) NULL DEFAULT NULL AFTER item_devolucao_condicao,
    ADD COLUMN item_devolucao_obs TEXT NULL DEFAULT NULL AFTER item_devolucao_destino,
    ADD COLUMN item_devolucao_vinculo_entrega_id INT NULL DEFAULT NULL AFTER item_devolucao_obs,
    ADD COLUMN item_devolucao_vinculo_item_id INT NULL DEFAULT NULL AFTER item_devolucao_vinculo_entrega_id,
    ADD COLUMN item_devolucao_tipo_operacao VARCHAR(50) NULL DEFAULT NULL AFTER item_devolucao_vinculo_item_id,
    ADD COLUMN item_motivo_entrega VARCHAR(50) NULL DEFAULT NULL AFTER item_devolucao_tipo_operacao;
