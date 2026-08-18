-- Migration: Adiciona coluna client_operation_id para suporte a reconciliação de operações
-- Execute este script no banco db_gestao_epi

ALTER TABLE Entrega_EPIs
    ADD COLUMN entr_client_operation_id VARCHAR(36) NULL AFTER entr_motivo;

CREATE UNIQUE INDEX idx_entr_client_operation_id ON Entrega_EPIs (entr_client_operation_id);
